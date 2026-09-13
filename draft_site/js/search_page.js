// To keep images from loading, their initial `src` attributes are set to ""

// However, once images have been loaded once, we don't want to 
// load them again. To hide images we don't reset their `src` attributes, we 
// just set the `display` style of the thumbnails div to "none".

import { addImageDivToggleListeners } from "./briels_helpful_code.js";

// Start thumbnails as hidden, images unloaded
const thumbnailState = { hidden: Boolean(true), loaded: Boolean(false) }
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

document.querySelectorAll(".toggle-nails")
        .forEach((btn) => btn.textContent = "Show thumbnails");

const params = new URLSearchParams(document.location.search);
const searchText = decodeURIComponent(params.get("search").replace(/\+/g, " "));
document.querySelectorAll(".search-comics")
        .forEach((field) => field.setAttribute("value", searchText));

if (params.get("desc") == "on") {
        document.querySelectorAll("input[name=\"desc\"]")
                .forEach((checkbox) => checkbox.setAttribute("checked", "CHECKED"));
}

if (params.get("exact") == "on") {
        document.querySelectorAll("input[name=\"exact\"]")
                .forEach((checkbox) => checkbox.setAttribute("checked", "CHECKED"));
}

