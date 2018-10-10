#include <iostream>
#include <fstream>
#include <cstdlib>
#include <string>
#include <cstring>
#include <vector>
#include <cctype>
using namespace std;

void filtNumber(char array[]);		// ------------- Function Prototypes -------------//
void filtLetter(char array[], int arg_Case);
void filtVowel(char array[], int arg_Case);
void filtConsonant(char array[], int arg_Case);
void filtPunct(char array[]);
void filtSpace(char array[]);
void filtWord(char array[]);
void filtWordUpper(char array[]);
void filtWordLower(char array[]);	
									// --------------------------- GLOBAL DECLARATIONS --------------------------//

char vowel[] = {'a','e','i','o','u','A','E','I','O','U'};		// Vowel and Consonant defined and stored in arrays for later comparisons.
char consonant[] = {'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','y','z','B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z'};

string input;						// Contains text from input file. This information later gets passed into a char array called inputArray[]. Filter functions will operate directly on inputArray[] instead of this input string.
vector<int> alnumIndex(10000);		// Contains the index of every Letter and Number in the input text. Necessary for recognizing words, and where they begin and end. (See: filtWordUpper & filtWordLower). Size initialized to 10000 just in case, later resized for an exact fit.
int indexTemp = 0;					// Tracks the number of letters and numbers when looping though the inputArray[]. (See: filtWordUpper & filtWordLower)

const char* fileName;				// Declared as const char* because ifstream's inputFile.open() only accepts this data type.

int main(int argc, char* argv[]){	//---------------------------------  MAIN  ----------------------------------//

	if (argc <= 2) { cout << "Not enough arguments. Provide the name/location of a text-file, followed by at least one filter argument. Example: input.txt upper vowel" << endl; exit(1); }
	
	if (argc > 2) {
	
		fileName = argv[1];		// File name retrieved as the first user-provided argument from the command line.

		ifstream inputFile;
		inputFile.open(fileName);	// Opens text file.

		if (inputFile.fail()) {		// Catching error on failure to open file.
			cout << "Error Opening File. Your first argument should be a valid text-file name/location." << endl;	exit(1);
		}
		
		string inputFileText( (istreambuf_iterator<char>(inputFile) ), (istreambuf_iterator<char>() ) );	// This extracts all the text from within the text file and temporarily holds it in inputFileText as a string.
		input = inputFileText;		// Copies the contents of the text file into the original input string, which is declared globally to be modified by all filter functions.
		inputFile.close();

	}

	char inputArray[input.length()];		// MASTER ARRAY. Char array created with length of the input string. All filter functions will operate directly on this array.
	strcpy(inputArray, input.c_str());		// Copies all characters in the input string into the char array. Necessary for proper looping later.
	
	string argVec[argc+1];		// Created to avoid operating directly on argv[]. Array is +1 bigger than argv[] due to complications with hitting the end of the array when looping though command line arguments.
	argVec[argc] = "";			// Second-to-last element set to null string to avoid aforementioned complications. This also becomes useful later for knowing when the last argument has been reached without going past the end of the array.
	
	for (int i=0; i<argc; i++) { argVec[i] = string(argv[i]); }		// Converts all argv[] command line arguments to strings and stores them in argVec[].

	for (int i=1; i<argc; i++) {		// MASTER ARGUMENT LOOP - HANDLES ALL INUPUTS FROM THE COMMAND LINE
	
		if (argVec[i] != "number" && argVec[i] != "space" && argVec[i] != "punct" && argVec[i] != "letter" && argVec[i] != "vowel" && argVec[i] != "consonant" && argVec[i] != "word" && argVec[i] != "upper" && argVec[i] != "lower" && argVec[i] != string(fileName) ) {
			cout << "One or more of your filter arguments is invalid. Select any valid combination of the following: \n\n upper \n lower \n letter \n vowel \n consonant \n word \n number \n space \n punct \n\n Example: input.txt upper vowel lower word punct \n" << endl;	exit(1);
		}		// Checks for invalid filter arguments.
	
		int argCase = 0;		// Will be used as an argument to certain filter functions. 1=uppercase, 2=lowercase, 0=default (no change).
		
		if (argVec[i] == "number") { filtNumber(inputArray); }		// Checks for command line argument. If matching, executes appropriate filter function and passes the inputArray into is as an argument.
		if (argVec[i] == "space") { filtSpace(inputArray); }
		if (argVec[i] == "punct") { filtPunct(inputArray); }
		
		if (argVec[i] == "upper") { 	// This ensures that the "upper" modifier is followed immediately by arguments letter/vowel/consonant/word ONLY.
			if (argVec[i+1] == "space" || argVec[i+1] == "punct" || argVec[i+1] == "number" || argVec[i+1] == "lower" || argVec[i+1] == "" ) {
				cout << "Modifiers such as 'upper' and 'lower' must be immediately followed by either 'letter', 'vowel', 'consonant' or 'word'." << endl;  exit(1); }
		}
		if (argVec[i] == "lower") { 	// This ensures that the "lower" modifier is followed immediately by arguments letter/vowel/consonant/word ONLY.
			if (argVec[i+1] == "space" || argVec[i+1] == "punct" || argVec[i+1] == "number" || argVec[i+1] == "upper" || argVec[i+1] == "" ) {
				cout << "Modifiers such as 'upper' and 'lower' must be immediately followed by either 'letter', 'vowel', 'consonant' or 'word'." << endl;  exit(1); }
		}
				
		if (argVec[i] == "letter") { 	// Filters letters. Checks if the previous command line argument argVec[i-1] is "upper" or "lower" or neither, and sets argCase accordingly.
			if (argVec[i-1] == "upper") { argCase=1; }
			if (argVec[i-1] == "lower") { argCase=2; }
			filtLetter(inputArray, argCase); argCase=0;		// argCase is used as an argument to the filter function. It then gets reset to 0 immediately to avoid contaminating the results of the following if-statements.
		}
		if (argVec[i] == "vowel") { 
			if (argVec[i-1] == "upper") { argCase=1; }
			if (argVec[i-1] == "lower") { argCase=2; }
			filtVowel(inputArray, argCase); argCase=0;
		}
		if (argVec[i] == "consonant") { 
			if (argVec[i-1] == "upper") { argCase=1; }
			if (argVec[i-1] == "lower") { argCase=2; }
			filtConsonant(inputArray, argCase); argCase=0;
		}
		if (argVec[i] == "word") { 		// Filters words. Checks if the previous command line argument argVec[i-1] is "upper" or "lower" or neither and executes the appropriate filter function.
			if (argVec[i-1] == "upper") { filtWordUpper(inputArray); }
			else if (argVec[i-1] == "lower") { filtWordLower(inputArray); }
			else { filtWord(inputArray); }
		}
			
			
	}

	
 	for (int i=0; i<input.length(); i++) {		// Loops through the filtered inputArray[]. Prints the final result to screen. 
		cout << inputArray[i];
	}	cout << endl;

	
return 0;

}

//---------------------------------  FILTER FUNCTIONS  ----------------------------------//


void filtNumber(char array[]) {		// Filters all numbers. All filter functions take inputArray[] as an argument.
	for (int i=0; i<input.length(); i++) {
		if (isdigit(array[i])) {
			array[i] = '\0';
		}		
	}
}

void filtLetter(char array[], int arg_Case) {		// Filters letters. Accepts argCase as an additional argument to determine uppercase/lowercase/neither.
	for (int i=0; i<input.length(); i++) {
		if (arg_Case == 1) {
			if (isalpha(array[i]) && isupper(array[i])) { array[i] = '\0'; }
		} else if (arg_Case == 2) {		
			if (isalpha(array[i]) && islower(array[i])) { array[i] = '\0'; }
		} else { if (isalpha(array[i])) { array[i] = '\0'; } }
	}
}

void filtVowel(char array[], int arg_Case) {
	for (int i=0; i<input.length(); i++) {
		for (int j=0; j < sizeof(vowel)/sizeof(*vowel); j++) {
			if (arg_Case == 1) {
				if (array[i] == vowel[j] && isupper(array[i])) { array[i] = '\0'; }		// Compares each character in inputArray[] to each character in vowel[] using two loops. Filters out the characters in inputArray[] that match.
			} else if (arg_Case == 2) {
				if (array[i] == vowel[j] && islower(array[i])) { array[i] = '\0'; }
			} else { if (array[i] == vowel[j]) { array[i] = '\0'; } }
		}
	}
}

void filtConsonant(char array[], int arg_Case) {
	for (int i=0; i<input.length(); i++) {
		for (int j=0; j < sizeof(consonant)/sizeof(*consonant); j++) {	
			if (arg_Case == 1) {
				if (array[i] == consonant[j] && isupper(array[i])) { array[i] = '\0'; }	
			} else if (arg_Case == 2) {
				if (array[i] == consonant[j] && islower(array[i])) { array[i] = '\0'; }
			} else { if (array[i] == consonant[j]) { array[i] = '\0'; } }
		}
	}
}

void filtPunct(char array[]) {		// Filters all punctuation marks.
	for (int i=0; i<input.length(); i++) {
		if (ispunct(array[i])) {
			array[i] = '\0';
		}		
	}
}

void filtSpace(char array[]) {		// Filters all punctuation spaces.
	for (int i=0; i<input.length(); i++) {
		if (isspace(array[i])) {
			array[i] = '\0';
		}		
	}
}

void filtWord(char array[]) {		// Filters all "words" by simply filtering out all letters and numbers.
	for (int i=0; i<input.length(); i++) {
		if (isalnum(array[i])) {
			array[i] = '\0';
		}		
	}
}

void filtWordUpper(char array[]) {		// Filters out a word only if every character within it is uppercase or a number, which defines an "uppercase word".

	for (int i=0; i<input.length(); i++) {

		if (isalnum(array[i])) {
			alnumIndex[indexTemp] = i;	// The alnumIndex[] vector stores the *index* (location) of all letters and numbers in inputArray[].
			indexTemp++;		// indexTemp is incremented each time a letter/number is found in inputArray[]. It provides the next index position for alnumIndex[] at each iteration.
		}		
	}
	alnumIndex.resize(indexTemp);		// Since the alnumIndex[] vector was originally initialized to 10000, this resizes it to be to be an exact fit for this purpose.
	
	int upper = 0, wordChar = 0, digit = 0;
	
	for (int i=0; i<indexTemp; i++) {
	
		wordChar++;		// wordChar increments at each iteration, but later gets set to 0 at the end of each word. This allows it to carry the exact size of each word by the final iteration.
		
		if (isupper(array[alnumIndex[i]]) || isdigit(array[alnumIndex[i]])) { upper++; }		// Checks whether or not each adjacent character in a word is uppercase. If so, "upper" gets incremented by one.
		if (isdigit(array[alnumIndex[i]])) { digit++; }
		
		if ( !isalnum(array[alnumIndex[i]+1]) ) {		// Checks if the NEXT character is NOT a letter/number. If it's not, then that's the end of the word.
			if (upper == wordChar && wordChar != digit) {				// Compares the size of "upper" and "wordChar". If they are equal in value at the end of a word, that means the number of characters in the word is equal to the number of uppercase characters in that word. Therefore, the word itself is uppercase.
				for (int j=0; j<wordChar; j++) {
					array[alnumIndex[i-j]] = '\0';		// Deletes every character from the beginning of the word to the end of the word, thus deleting the whole word.
				}
			}
			wordChar = 0;	// Since the end of a word was found, "wordChar" and "upper" are reset to 0 in preparation for the next word and the following iterations.
			upper = 0;
			digit = 0;
		}
	}
}

void filtWordLower(char array[]) {

	for (int i=0; i<input.length(); i++) {

		if (isalnum(array[i])) {
			alnumIndex[indexTemp] = i;
			indexTemp++;
		}		
	}
	alnumIndex.resize(indexTemp);
	
	int lower = 0, wordChar = 0, digit = 0;
	
	for (int i=0; i<indexTemp; i++) {
	
		wordChar++;
		
		if (islower(array[alnumIndex[i]]) || isdigit(array[alnumIndex[i]])) { lower++; }
		if (isdigit(array[alnumIndex[i]])) { digit++; }
		
		if ( !isalnum(array[alnumIndex[i]+1]) ) {
			if (lower == wordChar && wordChar != digit) {
				for (int j=0; j<wordChar; j++) {
					array[alnumIndex[i-j]] = '\0';
				}
			}
			wordChar = 0;
			lower = 0;
			digit = 0;
		}
	}
}