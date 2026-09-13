
const nextKeyCodes = new Set(["ArrowRight", 
                              "6", 
                              "d", 
                              "D",
                              "c", 
                              "C"]);

const prevKeyCodes = new Set(["ArrowLeft", 
                              "4", 
                              "a", 
                              "A",
                              "z", 
                              "Z"]);

import { addImageDivToggleListeners, getLink, keyNavigate } from "./briels_helpful_code.js";

// To keep images from loading, their initial `src` attributes are set to ""
// However, once images have been loaded once, we don't want to 
// load them again. To hide images we don't reset their `src` attributes, we 
// just set the `display` style of the thumbnails div to "none".
// Start thumbnails as visible, images loaded
const thumbnailState = { hidden: Boolean(false), loaded: Boolean(true) }
addImageDivToggleListeners(document, 
                           thumbnailState, 
                           ".toggle-nails", 
                           ".thumbnails-div", 
                           ".thumbnail", 
                           "attr-src", 
                           "--nails-display", 
                           "--nails-hide-display", 
                           "Hide thumbnails", 
                           "Show thumbnails");

document.addEventListener("keydown", (event) =>
        keyNavigate(event, 
                    window, 
                    prevKeyCodes, 
                    nextKeyCodes, 
                    getLink(document, ".later1"), 
                    getLink(document, ".earlier1"), 
                    getLink(document, ".later5"), 
                    getLink(document, ".earlier5"), 
                    ["input[type='search']"]
        )
);