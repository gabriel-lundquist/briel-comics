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

import {getLink, keyNavigate } from "./briels_helpful_code.js";

document.addEventListener("keydown", (event) => 
        keyNavigate(event, 
                    window,
                    prevKeyCodes, 
                    nextKeyCodes, 
                    getLink(document, ".prev-button"), 
                    getLink(document, ".next-button"),
                    getLink(document, ".prev-upd8-button"), 
                    getLink(document, ".next-upd8-button"), 
        )
);