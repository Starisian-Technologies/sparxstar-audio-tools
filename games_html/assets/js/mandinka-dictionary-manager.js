// DictionaryManager.js
window.AIWA_MandinkaQuiz = window.AIWA_MandinkaQuiz || {};

AIWA_MandinkaQuiz.DictionaryManager = (function() {
    let fullDictionary = [];
    let isLoaded = false;
    let isLoading = false;

    async function load(jsonPath) {
        if (isLoading || isLoaded) {
            // console.log("Dictionary load already in progress or completed.");
            return isLoaded;
        }
        isLoading = true;
        try {
            const response = await fetch(jsonPath);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const loadedWords = await response.json();
            if (loadedWords && Array.isArray(loadedWords) && loadedWords.length > 0) {
                fullDictionary = loadedWords;
                isLoaded = true;
                // console.log("DictionaryManager: Full dictionary loaded with", fullDictionary.length, "entries.");
                return true;
            } else {
                console.error("DictionaryManager: Loaded dictionary is not a valid array or is empty.");
                fullDictionary = []; // Ensure it's empty on failure
                isLoaded = false; // Explicitly set to false on error
                return false;
            }
        } catch (error) {
            console.error("DictionaryManager: Could not load dictionary:", error);
            isLoaded = false;
            fullDictionary = [];
            throw error; // Re-throw for the caller to handle UI
        } finally {
            isLoading = false;
        }
    }

    function getQuizWords(numWords = 10) {
        if (!isLoaded || fullDictionary.length === 0) {
            console.error("DictionaryManager: Dictionary not loaded or empty. Cannot get quiz words.");
            return [];
        }
        if (fullDictionary.length <= numWords) {
            // If dictionary is smaller than requested, return a shuffled copy of the whole dictionary
            return [...fullDictionary].sort(() => 0.5 - Math.random());
        }

        // Efficiently get N random unique words
        const selectedWords = [];
        const usedIndices = new Set();
        while (selectedWords.length < numWords && selectedWords.length < fullDictionary.length) {
            const randomIndex = Math.floor(Math.random() * fullDictionary.length);
            if (!usedIndices.has(randomIndex)) {
                selectedWords.push(fullDictionary[randomIndex]);
                usedIndices.add(randomIndex);
            }
        }
        return selectedWords; // Already somewhat random due to selection method
    }

    return {
        loadDictionary: load,
        getQuizWords: getQuizWords,
        isDictionaryLoaded: function() { return isLoaded; },
        getDictionarySize: function() { return fullDictionary.length; }
    };
})();