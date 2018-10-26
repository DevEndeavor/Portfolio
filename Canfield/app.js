$('.btn').click(function() {
    increment($(this).data('id'), $(this).parent().parent());
});

function increment(id, row) {
    $.ajax({
        type: "POST",
        url: "router.php",
        data: {"id": id},
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        success: function(data){
            updateView(data, row)
        }
    });
}

function updateView(data, row) {
    var updatedUserObj = JSON.parse(data)[0];
    row.find("td:eq(2)").html(updatedUserObj.access_count);
    row.find("td:eq(3)").html(updatedUserObj.modify_dt);
}