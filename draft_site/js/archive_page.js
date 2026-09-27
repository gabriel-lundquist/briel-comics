
const nextKeys = new Set([      "ArrowRight", 
                                "6", 
                                "d", 
                                "D",
                                "c", 
                                "C"     ]);

const prevKeys = new Set([      "ArrowLeft", 
                                "4", 
                                "a", 
                                "A",
                                "z", 
                                "Z"     ]);

import { addImageDivToggleListeners, getLink, keyNavigate, toggleImageDiv } from "./briels_helpful_code.js";

if (window.sessionStorage.getItem("showThumbnails") === null) window.sessionStorage.setItem("showThumbnails", "on");

const showThumbnailsSession = Boolean(window.sessionStorage.getItem("showThumbnails"));
if (!showThumbnailsSession) {
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

document.addEventListener("keydown", (event) =>
        keyNavigate(    event, 
                        window, 
                        prevKeys, 
                        nextKeys, 
                        getLink(document, ".later1"), 
                        getLink(document, ".earlier1"), 
                        getLink(document, ".later5"), 
                        getLink(document, ".earlier5"), 
                        ["input[type='search']"]
        )
);