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

import {/* getNavLinks,*/ getLink, keyNavigate } from "./briels_helpful_code.js";

// const navLinks = getNavLinks(document, 
//                              ".prev-button", 
//                              ".next-button", 
//                              ".prev-upd8-button", 
//                              ".next-upd8-button");

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