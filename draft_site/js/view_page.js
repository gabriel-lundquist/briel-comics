const nextKeys = new Set([  "ArrowRight", 
                            "6", 
                            "d", 
                            "D",
                            "c", 
                            "C" ]);

const prevKeys = new Set([  "ArrowLeft", 
                            "4", 
                            "a", 
                            "A",
                            "z", 
                            "Z" ]);

const widenKeys = new Set([ "=",
                            "+" ]);

const narrowKeys = new Set(["-"]);

const initPageWidth = 800;

const pageWidths = [200, 
                    400, 
                    600, 
                    800, 
                    1000, 
                    1200, 
                    1400, 
                    1600, 
                    2000, 
                    2400, 
                    2800, 
                    3200,
                    3600,
                    4000,
                    4400];

const doublePageWidths = pageWidths.map((width) => width * 2);

const widthFitMargin = "50px";

import { resizeNavImageMaps, noneHaveFocus, keyNavigate } from "./briels_helpful_code.js";

const body = document.querySelector("body");


// orientation toggle

/**
 * 
 * @param {*} element Must have a .sizes of the form "<Number>px".
 * @returns 
 */
function parseSizesSingle(element) {
    return Number(element.sizes.replace("px", ""));
}

function formatSizesSingle(num) {
    return num + "px";
}

function toggleOrientation() {
    body.classList.toggle("horizontal");
    const windowWidth = document.querySelector("html").clientWidth;
    if (body.classList.contains("horizontal")) {
        for (const display of document.querySelectorAll(".comic-display")) {
            const pageWidth = parseSizesSingle(display.querySelector(".comic-page"));
            display.style.padding = "0 max(0em, 50vw - 0.5 * " + pageWidth + "px)";
            if (pageWidth >= windowWidth) {
                display.style.margin = "0 " + widthFitMargin;
            }
        }
    } else {
        for (const display of document.querySelectorAll(".comic-display")) {
            display.style = null;
        }
    }
}

document.querySelectorAll("button.orient").forEach(
    (button) => button.addEventListener("click", toggleOrientation)
);


// increase or decrease image size

function getDisplayInfo(largerOrSmaller = "larger", root = document.querySelector("html")) {
    return Array.from(document.querySelectorAll(".comic-display")).map((display) => (
                {   display: display, 
                    page: display.querySelector(".comic-page"), 
                    oldWidth: parseSizesSingle(display.querySelector(".comic-page")), 
                    newWidth: largerOrSmaller == "larger" ? 
                            getLargerWidth(display.querySelector(".comic-page"), root)
                            : getSmallerWidth(display.querySelector(".comic-page"), root)   }
    ));
}

function pickWidthFromFit(newWidth, fitWindowWidthWidth, fitWindowHeightWidth) {
    if (fitWindowWidthWidth != newWidth && fitWindowHeightWidth != newWidth) {
        // choose the larger of the two fits
        return (fitWindowWidthWidth > fitWindowHeightWidth) ?
                fitWindowWidthWidth
                : fitWindowHeightWidth;
    } else if (fitWindowWidthWidth != newWidth) {
        return fitWindowWidthWidth;
    } else if (fitWindowHeightWidth != newWidth) {
        return fitWindowHeightWidth;
    } else {
        return newWidth;
    }
}

function getLargerWidth(img, root = document.querySelector("html")) {
    const oldWidth = parseSizesSingle(img);
    const isDoubleSpread = img.classList.contains("double");
    const imgPageWidths = isDoubleSpread ? doublePageWidths : pageWidths;

    let oldWidthIndex = imgPageWidths.indexOf(oldWidth);
    let newWidthIndex = (oldWidthIndex == imgPageWidths.length - 1) ? 
            oldWidthIndex 
            : oldWidthIndex + 1;
    if (oldWidthIndex == -1) {
        if (oldWidth < imgPageWidths[0]) 
            newWidthIndex = 0;
        else if (oldWidth > imgPageWidths[imgPageWidths.length - 1]) 
            return oldWidth; // Don't resize larger if we're at maximum
        else {
            for (   oldWidthIndex = 0; 
                    oldWidthIndex < imgPageWidths.length - 1; 
                    oldWidthIndex++    ) {
                if (oldWidth > imgPageWidths[oldWidthIndex]
                        && oldWidth < imgPageWidths[oldWidthIndex + 1]) {
                    newWidthIndex = oldWidthIndex + 1;
                    break;
                }
            }
        }
    }

    const windowWidth = root.clientWidth;
    const windowHeight = root.clientHeight;
    let newWidth = imgPageWidths[newWidthIndex];
    let fitWindowWidthWidth = newWidth;
    if (oldWidth < windowWidth && newWidth > windowWidth) {
        // fit to window width (takes priority)
        fitWindowWidthWidth = windowWidth;
    } 
    
    let fitWindowHeightWidth = newWidth;
    if (img.height < windowHeight 
            && (img.height / oldWidth) * newWidth > windowHeight) {
        // fit to window height
        fitWindowHeightWidth = (oldWidth / img.height) * windowHeight;
    }

    newWidth = pickWidthFromFit(newWidth, fitWindowWidthWidth, fitWindowHeightWidth);

    return newWidth;
}

function widenComicPages() {
    const root = document.querySelector("html");
    const pageDisplayInfo = getDisplayInfo("larger");
    let widenRatio = Infinity;
    for (const el of pageDisplayInfo) {
        if ( ! (el.page.classList.contains("double") ? 
                    doublePageWidths.includes(el.newWidth)
                    : pageWidths.includes(el.newWidth))    ) {
            let pageWidenRatio = el.newWidth / el.oldWidth;
            if (pageWidenRatio < widenRatio) widenRatio = pageWidenRatio;
        }
    }

    if (widenRatio < Infinity) {
        for (const el of pageDisplayInfo) {
            el.newWidth = el.oldWidth * widenRatio;
        }
    }

    const isHorizontal = document.querySelector("body").classList.contains("horizontal");
    for (const el of pageDisplayInfo) {
        // set the new width (accommodating for responsive srcset sizes)
        el.page.sizes = formatSizesSingle(el.newWidth);

        if (el.newWidth >= root.clientWidth || isWithinThreshold(el.newWidth, root.clientWidth))
            el.display.classList.add("wide-as-screen");

        if (isHorizontal) {
            el.display.style.padding = "0 max(0em, 50vw - 0.5 * " + el.newWidth + "px)";
            if (el.newWidth >= root.clientWidth) {
                el.display.style.margin = "0 " + widthFitMargin;
            }
        }
    }
}

function getSmallerWidth(img, root = document.querySelector("html")) {
    const oldWidth = parseSizesSingle(img);
    const isDoubleSpread = img.classList.contains("double");
    const imgPageWidths = isDoubleSpread ? doublePageWidths : pageWidths;

    let oldWidthIndex = imgPageWidths.indexOf(oldWidth);
    let newWidthIndex = (oldWidthIndex == 0) ? oldWidthIndex : oldWidthIndex - 1;
    if (oldWidthIndex == -1) {
        if (oldWidth < imgPageWidths[0]) 
            return oldWidth; // Don't resize smaller if we're at minimum
        else if (oldWidth > imgPageWidths[imgPageWidths.length - 1]) 
            newWidthIndex = imgPageWidths.length - 1;
        else {
            for (   oldWidthIndex = 1; 
                    oldWidthIndex < imgPageWidths.length; 
                    oldWidthIndex++    ) {
                if (oldWidth > imgPageWidths[oldWidthIndex - 1]
                        && oldWidth < imgPageWidths[oldWidthIndex]) {
                    newWidthIndex = oldWidthIndex - 1;
                    break;
                }
            }
        }
    }

    const windowWidth = root.clientWidth;
    const windowHeight = root.clientHeight;
    let newWidth = imgPageWidths[newWidthIndex];
    let fitWindowWidthWidth = newWidth;
    if (oldWidth > windowWidth && newWidth < windowWidth) {
        // fit to window width (takes priority)
        fitWindowWidthWidth = windowWidth;
    } 
    
    let fitWindowHeightWidth = newWidth;
    if (img.height > windowHeight 
            && (img.height / oldWidth) * newWidth < windowHeight) {
        // fit to window height
        fitWindowHeightWidth = (oldWidth / img.height) * windowHeight;
    }

    newWidth = pickWidthFromFit(newWidth, fitWindowWidthWidth, fitWindowHeightWidth);

    return newWidth;
}

function narrowComicPages() {
    const root = document.querySelector("html");
    const pageDisplayInfo = getDisplayInfo("smaller");
    let narrowRatio = 0;
    for (const el of pageDisplayInfo) {
        if ( ! (el.page.classList.contains("double") ? 
                    doublePageWidths.includes(el.newWidth)
                    : pageWidths.includes(el.newWidth))    ) {
            let pageNarrowRatio = el.newWidth / el.oldWidth;
            if (pageNarrowRatio > narrowRatio) narrowRatio = pageNarrowRatio;
        }
    }

    if (narrowRatio > 0) {
        for (const el of pageDisplayInfo) {
            el.newWidth = el.oldWidth * narrowRatio;
        }
    }

    const isHorizontal = document.querySelector("body").classList.contains("horizontal");
    for (const el of pageDisplayInfo) {
        // set the new width (accommodating for responsive srcset sizes)
        el.page.sizes = formatSizesSingle(el.newWidth);

        if (el.newWidth < root.clientWidth)
            el.display.classList.remove("wide-as-screen");

        if (isHorizontal) {
            el.display.style.padding = "0 max(0em, 50vw - 0.5 * " + el.newWidth + "px)";
            if (el.newWidth < root.clientWidth) {
                el.display.style.margin = "0";
            }
        }
    }
}

document.querySelectorAll("button.larger").forEach(
    (button) => button.addEventListener("click", widenComicPages)
);

document.querySelectorAll("button.smaller").forEach(
    (button) => button.addEventListener("click", narrowComicPages)
);

function resizeImageMaps(img) {
    const imgMap = document.querySelector("map[name='" + img.useMap.substr(1) + "']");
    resizeNavImageMaps( img, 
                        imgMap.querySelector("area.prev-button"), 
                        imgMap.querySelector("area.next-button")    );
}

document.querySelectorAll(".comic-page").forEach(
    (img) => img.addEventListener("load", () => resizeImageMaps(img))
);

window.addEventListener("load", () => document.querySelectorAll(".comic-page")
                                                .forEach((img) => resizeImageMaps(img)));

function keyResize(keyEvent, widenKeys, narrowKeys) {
    if (noneHaveFocus(["input[type='search']"])) {
        if (widenKeys.has(keyEvent.key)) {
            widenComicPages();
        } else if (narrowKeys.has(keyEvent.key)) {
            narrowComicPages();
        }
    }
}

// event code added below


// navigate with keys

function isWithinThreshold(coord1, coord2, threshold = 1) {
    return Math.abs(coord2 - coord1) <= threshold;
}

function isTakingUpScreenWidth(comicDisplay) {
    const screenWidth = document.querySelector("html").clientWidth;
    const comicWidth = comicDisplay.querySelector(".comic-page").clientWidth;
    return comicWidth >= screenWidth || isWithinThreshold(screenWidth, comicWidth);
}

/**
 * Assumes that the header and all of the displays have 0 margin on the relevant axis
 */
function getComicScrollEndCoords() {
    const isHorizontal = document.querySelector("body").classList.contains("horizontal");
    const getOffsetLength = isHorizontal ? 
            (element) => element.offsetWidth 
            : (element) => element.offsetHeight;
    const widthFitMarginPx = Number(widthFitMargin.replace("px", ""));
    const displays = document.querySelectorAll(".comic-display");

    const coords = Array(displays.length + 2);
    coords[0] = getOffsetLength(document.querySelector("header")) 
            + (isHorizontal && isTakingUpScreenWidth(displays[0]) ? widthFitMarginPx : 0);
    let i = 0;
    for (i = 0; i < displays.length - 1; i++) {
        coords[i + 1] = coords[i] 
                        + getOffsetLength(displays[i]) 
                        + (isHorizontal && isTakingUpScreenWidth(displays[i]) ?
                                widthFitMarginPx : 0)
                        + (isHorizontal && isTakingUpScreenWidth(displays[i + 1]) ? 
                                widthFitMarginPx : 0);
    }
    coords[i + 1] = coords[i] + getOffsetLength(displays[i]);

    // coords[0] = 0;
    // coords[1] = getOffsetLength(document.querySelector("header"));
    // for (let i = 0; i < displays.length; i++) {
    //     coords[i + 2] = coords[i + 1] + getOffsetLength(displays[i]);
    // }

    // const endCoords = Array(displays.length);
    // endCoords[0] = getOffsetLength(document.querySelector("header")) 
    //                 + getOffsetLength(displays[i]);
    // for (let i = 1; i < displays.length; i++) {
    //     endCoords[i] = endCoords[i - 1] + getOffsetLength(displays[i]);
    // }

    return coords;
}

function getComicInViewport(
        coords, 
        isHorizontal = document.querySelector("body").classList.contains("horizontal")
    ) {
    let scrollCoord = Math.round(
            isHorizontal ? window.scrollX : window.scrollY
    );

    let elementIndex = 0; 
    for (elementIndex = 0; elementIndex < coords.length; elementIndex++) {
        if (coords[elementIndex] > scrollCoord 
                // || isWithinThreshold(coords[elementIndex], scrollCoord) 
                ) 
            break;
    }
    // elementIndex is now the index of the element (either header, comic-display, or footer)
    // that is at least centered in your view, if not further back

    return elementIndex;
}

function getPrevComicLink(elementIndex, mapList = document.querySelectorAll("map")) {
    // subtract 1 since elementIndex = 0 corresponds to header.
    console.log("Getting previous link");
    const comicIndex = elementIndex - 1;
    const getMapPrevHref = (index) => mapList[index].querySelector(".prev-button").href;
    if (comicIndex < 0) return getMapPrevHref(0); // we're in the header
    else if (comicIndex >= mapList.length) // we're in the footer
        return mapList[mapList.length - 1].querySelector(".next-button").href;
    else {
        // subtract 1 since elementIndex = 0 corresponds to header.
        return getMapPrevHref(comicIndex);
    }
}

function getCurrComicLink(elementIndex, mapList = document.querySelectorAll("map")) {
    console.log("Getting current link");
    const comicIndex = elementIndex - 1;
    if (comicIndex <= 0) {
        return mapList[1].querySelector(".prev-button").href;
    } else if (elementIndex >= mapList.length) {
        return mapList[mapList.length - 1].querySelector(".next-button").href;
    } else {
        return mapList[comicIndex + 1].querySelector(".prev-button").href;
    }
}

function getNextComicLink(elementIndex, mapList = document.querySelectorAll("map")) {
    // subtract 1 since elementIndex = 0 corresponds to header.
    const comicIndex = elementIndex - 1;
    const getMapNextHref = (index) => mapList[index].querySelector(".next-button").href;
    if (comicIndex < 0) // we're in the header
        return mapList[0].querySelector(".prev-button").href;
    else if (comicIndex >= mapList.length) // we're in the footer
        return getMapNextHref(mapList.length - 1);
    else {
        return getMapNextHref(elementIndex - 1);
    }
}

// kinda a kludge... there's a more elegant way where I have to rewrite some of keyNavigate
document.addEventListener("keydown", function (event) {
        if (widenKeys.has(event.key) || narrowKeys.has(event.key)) 
            keyResize(event, widenKeys, narrowKeys);
        else if (prevKeys.has(event.key) || nextKeys.has(event.key)) {
            event.preventDefault();
            let scrollCoord = window.scrollY;
            if (document.querySelector("body").classList.contains("horizontal")) {
                scrollCoord = window.scrollX;
            }
            const coords = getComicScrollEndCoords();
            console.log("Scroll coord: " + scrollCoord);
            console.log(coords);
            const elementIndex = getComicInViewport(coords);
            console.log("Element index: " + elementIndex);
            const mapList = document.querySelectorAll("map");
            const backLink = coords.some((val) => isWithinThreshold(val, scrollCoord)) ? 
                                    getPrevComicLink(elementIndex, mapList)
                                    : getCurrComicLink(elementIndex, mapList);
            console.log("Back link: " + backLink);
            keyNavigate(event, 
                        window, 
                        prevKeys, 
                        nextKeys, 
                        backLink, 
                        getNextComicLink(elementIndex, mapList), 
                        "", 
                        "", 
                        ["input[type='search']"]
            );
        }
        
    }
);