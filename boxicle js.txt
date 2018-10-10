< script >

	$("#fileUploadForm").change(function () {
		$("#fileUploadForm").submit();
	});

$("#fileUploadForm").submit(function () {
	var fileChooser = document.getElementById('fileinput');
	var file = fileChooser.files[0];
	getPermissionToUpload(file);
	return false;
});

AWS.config.region = 'us-east-1';

function getPermissionToUpload(file) {
	var timenow = Math.round((new Date()).getTime() / 1000);

	if (AWS.config.expires === undefined || AWS.config.expires - timenow <= 180) {
		AWS.config.ajaxGetCred = $.ajax({
			type: "POST",
			url: "http://boxicle.com/creds",
			data: {
				'_token': $('meta[name=csrf-token]').attr('content')
			}
		}).done(function (data) {
			var creds = JSON.parse(atob(atob(data)));
			AWS.config.credentials = new AWS.Credentials({
				accessKeyId: creds['key'],
				secretAccessKey: creds['secret'],
				sessionToken: creds['token']
			});
			AWS.config.expires = creds['expires'];
			AWS.config.sid = creds['sid'];
			sessionStorage.setItem('AWScreds', data);
			localStorage.setItem('AWScreds', data);

		});
	} else {
		$.ajax({
			type: "POST",
			url: "http://boxicle.com/permission",
			data: {
				name: file.name,
				type: file.type,
				size: file.size,
				sid: AWS.config.sid,
				parent_fileid: $('.row-group').data('parent_fileid'),
				'_token': $('meta[name=csrf-token]').attr('content')
			}
		}).done(function (data) {
			var metadata = JSON.parse(data);
			uploadFilesToS3(file, metadata);
		});

	return;
}

	$.when(AWS.config.ajaxGetCred).done(function () {
		$.ajax({
			type: "POST",
			url: "http://boxicle.com/permission",
			data: {
				name: file.name,
				type: file.type,
				size: file.size,
				sid: AWS.config.sid,
				parent_fileid: $('.row-group').data('parent_fileid'),
				'_token': $('meta[name=csrf-token]').attr('content')
			}
		}).done(function (data) {
			var metadata = JSON.parse(data);
			uploadFilesToS3(file, metadata);
		});
	});

}

function uploadFilesToS3(file, metadata) {
	if (file) {
		var bucket = new AWS.S3({
		params: {
		Bucket: 'boxicle/' + metadata['user']
}
});
		var params = {
		Key: metadata['guid'],
	ContentType: file.type,
	Body: file,
			Metadata: {
		name: metadata['name'],
	user: String(metadata['user']),
	size: String(metadata['size']),
	parent: String(metadata['parent_id']),
	time: String(metadata['time']),
	signature: metadata['signature']
}
};
var uploadReq = bucket.upload(params);
		uploadReq.on('httpUploadProgress', function (evt) {
			var progress = "Uploaded: " + parseInt((evt.loaded * 100) / evt.total) + '%';
	$('#progressbar').html(progress);
});
		uploadReq.send(function (err, data) {

			if (err) {
		console.log(err);
	return;
}

			$.post("http://boxicle.com/upload", {
		name: file.name,
	type: file.type,
	size: file.size,
	guid: metadata['guid'],
	signature: metadata['signature'],
	parent_fileid: $('.row-group').data('parent_fileid'),
	'_token': $('meta[name=csrf-token]').attr('content')
			}).done(function (data) {
		$('#progressbar').html(data);
	getTree();

});
});
}
}

function fileDrop(e) {
		e.preventDefault();
	getPermissionToUpload(e.dataTransfer.files[0]);
}

$(window).on('storage', function (e) {
	if (e.originalEvent.key == 'aws.test-storage') return;

	if (e.originalEvent.key == 'AWScreds') {
		var Data = e.originalEvent.newValue;
	sessionStorage.setItem('AWScreds', Data);
	var creds = JSON.parse(atob(atob(Data)));
		AWS.config.credentials = new AWS.Credentials({
		accessKeyId: creds['key'],
	secretAccessKey: creds['secret'],
	sessionToken: creds['token']
});
AWS.config.expires = creds['expires'];
AWS.config.sid = creds['sid'];
localStorage.clear();
return;
}

var fileid = e.originalEvent.key;
var data = e.originalEvent.newValue;
	if (data == 'delete') {
		sessionStorage.removeItem(fileid);
	if (fileid == $('.row-group').data('parent_fileid')) {
		location.reload(true);
	}
	localStorage.clear();
	return;
}
sessionStorage.setItem(fileid, data);
	if (fileid == $('.row-group').data('parent_fileid')) {
		$('.row-group').replaceWith(data);
	}
	localStorage.clear();
});

function getTree() {
	var fileid = $('.row-group').data('parent_fileid');
	window.getFileGroup(fileid, true);
	setTimeout(function () {
		localStorage.setItem(fileid, $('.row-group').get(0).outerHTML);
	}, 2000);
}

function uploadFiles() {
		$('#fileinput').trigger('click');
	}
	
function downloadFile() {
	var file = $('.selected').first();
	if (file.data('type') != "folder") {
		$.post("http://boxicle.com/download", {
			fileid: file.data('fileid'),
			'_token': $('meta[name=csrf-token]').attr('content')
		}).done(function (data) {
			window.location = data;
		});
	}
}

function createFolder() {
		$.post("http://boxicle.com/create", {
			parent_fileid: $('.row-group').data('parent_fileid'),
			'_token': $('meta[name=csrf-token]').attr('content')
		}).done(function (data) {
			$('#progressbar').html(data);
			getTree();
		});
	}
	
function copyFiles() {
	var file_ids = [];
	$('.selected').each(function () {
		file_ids.push($(this).data('fileid'));
	});
	$.post("http://boxicle.com/copy", {
		fileids: file_ids,
	'_token': $('meta[name=csrf-token]').attr('content')
	}).done(function (data) {
		$('#progressbar').html(data);
	getTree();
});
}

function renameFile() {
		$.post("http://boxicle.com/rename", {
			'_token': $('meta[name=csrf-token]').attr('content')
		}).done(function (data) {
			$('#progressbar').html(data);
			getTree();
		});
	}
	
function removeFiles() {
	var file_ids = [];
	$('.selected').each(function () {
		file_ids.push($(this).data('fileid'));
	});
	$.post("http://boxicle.com/remove", {
		fileids: file_ids,
	'_token': $('meta[name=csrf-token]').attr('content')
	}).done(function (data) {
		$('#progressbar').html(data);
	getTree();
});
} <
/script>
	
<
		script >

		var primaryRowSelected;
	
	var selectCount = 0;
	var ctrlKey = false;
	var shiftKey = false;
	var seldivNoCtrl = true;
	var dragging = false;
	var rightclick = false;
	var contextmenu = false;
	var folderDragOver = null;
	
function outlineCells(bool) {
	var color = (bool ? '1px solid #eee' : '1px solid transparent');
	
	if (bool) {
		if (selectCount > 0) {
			color = '1px solid lightblue';
		$(primaryRowSelected).find('.cell').css({
			'border-bottom': color
	});
}
		$(primaryRowSelected).find('.cell').css({
			'border-top': color
	});
		$(primaryRowSelected).find('.cell:first-child').css({
			'border-left': color
	});
		$(primaryRowSelected).find('.cell:last-child').css({
			'border-right': color
	});
	} else {
			$('.row').each(function () {
				$(this).find('.cell').css({
					'border-top': color
				});
				$(this).find('.cell').css({
					'border-bottom': '1px solid #eee'
				});
				$(this).find('.cell:first-child').css({
					'border-left': color
				});
				$(this).find('.cell:last-child').css({
					'border-right': color
				});
			});
		}
	
	}
	
$(document).keydown(function (e) {
	if (e.keyCode == 17) {
			ctrlKey = true;
		outlineCells(true);
	}
	if (e.keyCode == 16) {
			shiftKey = true;
		outlineCells(true);
	}
	if (ctrlKey && e.keyCode == 65) {
			$('.row').addClass('selected');
		}
	if (e.keyCode == 27) {
			$('.row').removeClass('selected').removeClass('preselected').removeClass('shiftselected');
		}
	if (e.keyCode == 38) {
			outlineCells(false);
		primaryRowSelected = $(primaryRowSelected).prev();
		outlineCells(true);
	}
	if (e.keyCode == 40) {
			outlineCells(false);
		primaryRowSelected = $(primaryRowSelected).next();
		outlineCells(true);
	}
});
$(document).keyup(function (e) {
	if (e.keyCode == 17) ctrlKey = false;
		if (e.keyCode == 16) shiftKey = false;
	});
	
$(document).ready(function () {
			primaryRowSelected = $('.row').first();

		$(document).on('mousedown', function (e) {
		if (e.which > 3) return;
		if (!contextmenu) {
			$('.row').each(function () {
				if (!$(e.target).parent().hasClass('row')) {
					$(this).removeClass('preselected').removeClass('selected');
				}
				if ($(this).hasClass('selected') || $(this).hasClass('preselected')) selectCount++;
				if (!$(this).hasClass('selected') && !$(this).hasClass('preselected')) $(this).removeClass('shiftselected');
			});
		outlineCells(false);
		selectCount = $('.selected').length;
	}
});

	$('body').on('mousedown', '.row', function (e) {
		if (e.which > 3) return;
		outlineCells(false);
		if (e.which == 3) rightclick = true;
		if (!shiftKey) primaryRowSelected = $(this);

		if (ctrlKey && !rightclick) {
			if ($(this).hasClass('selected')) {
			seldivNoCtrl = false;
		$(this).removeClass('preselected').removeClass('selected');
			} else {
			$(this).addClass('preselected');
		}
		} else if (shiftKey && !rightclick) {
			$('.row').each(function () {
				if ($(this).hasClass('shiftselected')) {
					$(this).removeClass('shiftselected').removeClass('selected');
				}
			});
		if ($(primaryRowSelected).index() < $(this).index()) {
			$(primaryRowSelected).nextUntil($(this)).addBack().add($(this)).addClass('preselected').addClass('shiftselected');
		} else {
			$(primaryRowSelected).prevUntil($(this)).addBack().add($(this)).addClass('preselected').addClass('shiftselected');
		}

		} else {
			if (selectCount <= 1) {
			$('.row').each(function () {
				if ($(e.target).parent()[0] != $(this)[0]) {
					$(this).removeClass('preselected').removeClass('selected');
				}
			});
		} else if (selectCount > 0 && !$(this).hasClass('selected')) {
			$('.row').each(function () {
				$(this).removeClass('preselected').removeClass('selected');
			});
		}

			if (!$(this).hasClass('selected')) {
			$(this).addClass('preselected');
		}
	}
});
	$(document).on('mouseup', function (e) {
		if (!ctrlKey && !shiftKey && !dragging && !rightclick && !contextmenu) {
			if (selectCount > 1) {
			$('.row').each(function () {
				if ($(e.target).parent()[0] != $(this)[0]) {
					$(this).removeClass('preselected').removeClass('selected');
				}
			});
		}
	}
		$('.row').each(function () {
			if ($(this).hasClass('preselected') || $(this).hasClass('selected')) {
			$(this).addClass('selected').removeClass('preselected');
		}
	});

		if (dragging && folderDragOver != null) {
			var draggedFiles = $('.selected').not(folderDragOver);
		var parent_fileid = folderDragOver.data('fileid');
		var file_ids = [];
			draggedFiles.each(function () {
			file_ids.push($(this).data('fileid'));
		});
			$.post("http://boxicle.com/move", {
			fileids: file_ids,
		parent_fileid: parent_fileid,
		'_token': $('meta[name=csrf-token]').attr('content')
			}).done(function (data) {
				if (data) {
			draggedFiles.hide();
		$('#progressbar').html(data);
		getTree();

		localStorage.setItem(parent_fileid, 'delete');
	}
});
}

rightclick = false;
dragging = false;
folderDragOver = null;
selectCount = $('.selected').length;
$('.contextmenu').hide();
contextmenu = false;
});

	(function () {
		var oldPath = '/';
		setInterval(function () {
			var path = window.location.pathname;
			if (path != oldPath) {
				var fileid = path.split("/")[1] == "folders" ? path.split("/")[2] : "root";

				if ($('.row-group').data('parent_fileid') != fileid) {
			getFileGroup(fileid);
		}
		oldPath = path;
	}
}, 100);
})();

	$('body').on('dblclick', '.row', function () {
		var id = $(this).data('id');
		var fileid = $(this).data('fileid');
		if ($(this).data('filetype') == "folder") {
			var url = window.location.pathname != "/" ? fileid : "folders/" + fileid;
		history.pushState(null, null, url);
		getFileGroup(fileid);
	}
});

	window.getFileGroup = function (fileid, forceRequest = false) {
		if (sessionStorage.getItem(fileid) && !forceRequest) {
			var data = sessionStorage.getItem(fileid);
		$('.row-group').data('parent_fileid', fileid);
		$('.row-group').replaceWith(data);
		} else {
			$.post("http://boxicle.com/tree", {
				fileid: fileid,
				'_token': $('meta[name=csrf-token]').attr('content')
			}).done(function (data) {

				var fileGroup = generateFileGroup(data);
				$('.row-group').replaceWith(fileGroup);
				sessionStorage.setItem(fileid, fileGroup);
			});
		}
	};

	(function () {
		if (window.location.pathname == "/") {
			var data = $('.row-group').get(0).outerHTML;
		history.replaceState(null, null, "");
		sessionStorage.setItem('root', data);
		} else if (window.location.pathname.split("/")[1] == "folders") {
			var data = $('.row-group').get(0).outerHTML;
		history.replaceState(null, null, window.location.pathname.split("/")[2]);
		var fileid = $('.row-group').data('parent_fileid');
		sessionStorage.setItem(fileid, data);
	}
})();

	function generateFileGroup(obj) {

		var fileGroup = '<div class="row-group" data-parent_fileid="' + obj['parent_fileid'] + '">';

		$.each(obj['files'], function (index, file) {
			var newFile = '<div class="row" data-filetype="' + file['type'] + '" data-fileid="' + file['fileid'] + '">' +
				'<div class="cell">' + file['name'] + '</div>' +
				'<div class="cell">me</div>' +
				'<div class="cell">' + file['updated_at'] + ' me</div>' +
				'<div class="cell">' + file['size'] + '</div>' +
				'</div>';

		fileGroup = fileGroup.concat(newFile);
	});
		fileGroup = fileGroup.concat('</div>');

		return fileGroup;
	}

});

var mousedown = false;
var initPosSet = false;
var initPosX = 0;
var initPosY = 0;
var targetSet = false;
var target;

$(document).mousedown(function (e) {
			document.getSelection().removeAllRanges();
		if (e.which == 1) mousedown = true;
		if (e.which == 3) rightclick = true;
		targetSet = !$(e.target).parent().hasClass('selected');
		target = $(e.target);
	});
$(document).mouseup(function () {
			mousedown = false;
		$('#seldiv').hide();
		$('#tail').hide();
		initPosSet = false;
		targetSet = false;
		seldivNoCtrl = true;
		target = false;
	$('.selected').css({
			'opacity': '1'
	});
});

$(document).on('mousemove', function (e) {

			$('#tail').css({
				left: e.pageX + 14,
				top: e.pageY
			});

		if (mousedown && targetSet && seldivNoCtrl && !contextmenu) {

		if (!initPosSet) {
			initPosX = e.pageX;
		initPosY = e.pageY;
		initPosSet = true;
	}

	var posX = e.pageX;
	var posY = e.pageY;
	var quadrant = 0;
	var newquad = false;
	var seldiv = $('#seldiv');

		if (posX - initPosX >= 0 && posY - initPosY >= 0) {
			newquad = (quadrant != 4 || !quadrant);
		quadrant = 4;
	}
		if (posX - initPosX < 0 && posY - initPosY >= 0) {
			newquad = (quadrant != 3 || !quadrant);
		quadrant = 3;
	}
		if (posX - initPosX < 0 && posY - initPosY < 0) {
			newquad = (quadrant != 2 || !quadrant);
		quadrant = 2;
	}
		if (posX - initPosX >= 0 && posY - initPosY < 0) {
			newquad = (quadrant != 1 || !quadrant);
		quadrant = 1;
	}

		if (newquad) {
			var docX = window.innerWidth;
		var docY = window.innerHeight;

			if (quadrant == 4) {
			seldiv.show().css({
				left: initPosX,
				top: initPosY,
				right: 'unset',
				bottom: 'unset'
			});
		}
			if (quadrant == 3) {
			seldiv.show().css({
				right: docX - initPosX,
				top: initPosY,
				left: 'unset',
				bottom: 'unset'
			});
		}
			if (quadrant == 2) {
			seldiv.show().css({
				right: docX - initPosX,
				bottom: docY - initPosY,
				left: 'unset',
				top: 'unset'
			});
		}
			if (quadrant == 1) {
			seldiv.show().css({
				left: initPosX,
				bottom: docY - initPosY,
				right: 'unset',
				top: 'unset'
			});
		}
	}

		seldiv.css({
			width: Math.abs(posX - initPosX),
		height: Math.abs(posY - initPosY)
	});

		$('.row').each(function () {
			var rowtop = $(this).position().top;
		var rowbottom = rowtop + $(this).height();
		var seldivtop, seldivbottom;
			if (posY - initPosY >= 0) {
			seldivtop = initPosY;
		seldivbottom = posY;
			} else {
			seldivtop = posY;
		seldivbottom = initPosY;
	}

			if (seldivtop < rowbottom && seldivbottom > rowtop) {
			$(this).addClass('preselected');
		} else {
			$(this).removeClass('preselected');
		}
	});
}

	if (mousedown && $(target).parent().hasClass('selected')) {
			dragging = true;
		var tail = $('#tail');
		tail.css({
			left: e.pageX + 14,
		top: e.pageY
	});
	tail.html(selectCount + ' item(s)');
	tail.show();

		$('.selected').css({
			'opacity': '0.2'
	});

	$('.contextmenu').hide();
}

});

$(document).ready(function () {
			$('body').on('mouseenter', '.row', function () {
				if (dragging && $(this).data('filetype') == 'folder') {
					if (!$(this).hasClass('selected')) {
						$(this).addClass('preselected');
						folderDragOver = $(this);
					}
				}

			});
		$('body').on('mouseleave', '.row', function () {
		if (dragging && $(this).data('filetype') == 'folder') {
			$(this).removeClass('preselected');
		folderDragOver = null;
	}
});

});

$(document).contextmenu(function (e) {
	var cmenu = $('.contextmenu');
		var posX = e.pageX;
		var posY = e.pageY;
		var docX = window.innerWidth;
		var docY = window.innerHeight;
		var menuY = cmenu.height();
		var menuX = cmenu.width();
		var quad = posY + menuY >= docY ? 1 : 3;
		quad = posX + menuX >= docX ? quad + 1 : quad;
	if (quad == 3) {
			cmenu.css({
				left: posX,
				top: posY,
				right: 'unset',
				bottom: 'unset'
			}).slideDown(100);
		}
	if (quad == 4) {
			cmenu.css({
				right: docX - posX,
				top: posY,
				left: 'unset',
				bottom: 'unset'
			}).slideDown(100);
		}
	if (quad == 2) {
			cmenu.css({
				right: docX - posX,
				bottom: docY - posY,
				left: 'unset',
				top: 'unset'
			}).slideDown(100);
		}
	if (quad == 1) {
			cmenu.css({
				left: posX,
				bottom: docY - posY,
				right: 'unset',
				top: 'unset'
			}).slideDown(100);
		}
	
		contextmenu = true;
	
		return false;
	});
	
	
</script>
