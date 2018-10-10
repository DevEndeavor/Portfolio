import pandas as pd
import requests
import urllib

API_KEY = "xxxxxxxxxx"		# Google Books
INPUT_FILE = "C:/Users/USER/Desktop/bookscsv.csv"
OUTPUT_FILE = "C:/Users/USER/Desktop/outputcsv.csv"

df = pd.read_csv(INPUT_FILE, delimiter=',', error_bad_lines=False, encoding='latin1', low_memory=False, skip_blank_lines=True)

unique_books = df["Item Title"].unique()

newdata = {'Item':unique_books, 'Qty':[0]*unique_books.size, 'Net':[0]*unique_books.size, 'Publisher':[None]*unique_books.size}

newdf = pd.DataFrame.from_dict(newdata)
newdf.set_index('Item', inplace=True)

i = 0

for index,row in df.iterrows():
	title = df.loc[index]['Item Title']

	try:
		newdf.loc[title]['Qty'] += 1
		newdf.loc[title]['Net'] += float(df.loc[index]['Net'])
		
		if (newdf.loc[title]['Qty'] == 1):
			try:
				urltitle = urllib.parse.quote_plus(title)

				req = requests.get('https://www.googleapis.com/books/v1/volumes?key='+API_KEY+'&q='+urltitle)

				j = req.json()

				try:				
					publisher = j['items'][0]['volumeInfo']['Publisher']
					newdf.loc[title]['Publisher'] = publisher
					print("i: {0}, title: {1}, publisher: {2}".format(i, title, publisher))
				except:
					publisher = False

				if (publisher == False):
					try:
						publisher = j['items'][1]['volumeInfo']['Publisher']
						newdf.loc[title]['Publisher'] = publisher
						print("i: {0}, PASS: {1}, publisher: {2}".format(i, title, publisher))
					except:
						pass
			except:
				print("PASSPUB: {0}, PASSPUB: {1}".format(i, title))
				pass
		
		else:
			print("i: {0}, title: {1}".format(i, title))
		
	except:
		print("PASS: {0}, PASS: {1}".format(i, title))
		#pass
			
	i += 1
	
	
newdf.to_csv(OUTPUT_FILE, sep=',', encoding='latin1')

print(newdf)