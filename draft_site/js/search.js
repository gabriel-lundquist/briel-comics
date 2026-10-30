// To keep images from loading, their initial `src` attributes are set to ""

// However, once images have been loaded once, we don't want to 
// load them again. To hide images we don't reset their `src` attributes, we 
// just set the `display` style of the thumbnails div to "none".

import { addElementToggleListeners, addImageDivToggleListeners, toggleElementsVisibilityWithButtonsSelectors, toggleImageDiv } from "./briels_helpful_code.js";
if (window.sessionStorage.getItem("showThumbnails") === null) window.sessionStorage.setItem("showThumbnails", "on");
if (window.sessionStorage.getItem("showDescriptions") === null) window.sessionStorage.setItem("showDescriptions", "on");

const showThumbnailsSession = Boolean(window.sessionStorage.getItem("showThumbnails"));
if (!showThumbnailsSession) { // not default
        toggleImageDiv( {isHidden: false, isLoaded: false}, 
                        document.querySelectorAll(".toggle-nails"), 
                        document.querySelectorAll(".thumbnails-div"), 
                        document.querySelectorAll(".thumbnail"), 
                        "attr-src", 
                        "Hide thumbnails", 
                        "Show thumbnails", 
                        false
        );
}
const thumbnailVisibilityState = { isHidden: !showThumbnailsSession, isLoaded: showThumbnailsSession };
addImageDivToggleListeners(     document, 
                                thumbnailVisibilityState, 
                                ".toggle-nails", 
                                ".thumbnails-div", 
                                ".thumbnail", 
                                "attr-src", 
                                "Hide thumbnails", 
                                "Show thumbnails"       );

// document.querySelectorAll(".toggle-nails")
//         .forEach((btn) => btn.textContent = "Show thumbnails");

const showDescriptionSession = Boolean(window.sessionStorage.getItem("showDescriptions"));
const descriptionVisibilityState = { isHidden: !showDescriptionSession };
addElementToggleListeners(      document, 
                                descriptionVisibilityState, 
                                ".toggle-desc", 
                                ".desc-para", 
                                "Hide descriptions", 
                                "Show descriptions", 
                                "showDescriptions"
);
if (!showDescriptionSession) { //not default
        toggleElementsVisibilityWithButtonsSelectors(   descriptionVisibilityState, 
                                                        ".desc-para", 
                                                        ".toggle-desc", 
                                                        "Hide descriptions", 
                                                        "Show descriptions", 
                                                        "showDescriptions", 
                                                        "showDescriptions"      )
}

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

