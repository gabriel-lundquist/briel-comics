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

import {getLink, keyNavigate, resizeNavImageMaps} from "./briels_helpful_code.js";

document.addEventListener("keydown", (event) => 
        keyNavigate(event, 
                    window,
                    prevKeyCodes, 
                    nextKeyCodes, 
                    getLink(document, ".prev-button"), 
                    getLink(document, ".next-button"),
                    getLink(document, ".prev-upd8-button"), 
                    getLink(document, ".next-upd8-button"), 
                    ["input[type='search']"]
        )
);

const comicImg = document.querySelector("img.comic-page");
const areaPrevButton = document.querySelector("area.prev-button");
const areaNextButton = document.querySelector("area.next-button");

window.addEventListener("load", (event) =>
        resizeNavImageMaps(comicImg, areaPrevButton, areaNextButton)
);

window.addEventListener("resize", (event) =>
        resizeNavImageMaps(comicImg, areaPrevButton, areaNextButton)
);

const interactGraphics = document.querySelectorAll(".interactive-graphic");
function switchDisplays(element1, element2) {
        const tempDisplay = window.getComputedStyle(element1).display;
        element1.style.display = window.getComputedStyle(element2).display;
        element2.style.display = tempDisplay;
}
interactGraphics.forEach((graphic) => {
        const resting = graphic.querySelector(".resting");
        const interacting = graphic.querySelector(".interacting");
        resting.addEventListener("click", () => switchDisplays(resting, interacting));
        resting.addEventListener("mouseenter", () => switchDisplays(resting, interacting));
        resting.addEventListener("mouseleave", () => switchDisplays(resting, interacting));
        interacting.addEventListener("click", () => switchDisplays(resting, interacting));
        interacting.addEventListener("mouseenter", 
                () => switchDisplays(resting, interacting));
        interacting.addEventListener("mouseleave", 
                () => switchDisplays(resting, interacting));
        // clear javascript's applied styles on image resize
        window.addEventListener("resize", 
                () => resting.style.display = interacting.style.display = "");
});