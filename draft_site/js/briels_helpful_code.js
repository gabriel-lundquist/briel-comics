/**
 * Intended use is to toggle the visibility of a class of divs which contain images.
 * Expects a custom attribute on the image elements that stores a path to their 
 * source data, and another custom attribute that stores their preferred display 
 * values when shown and hidden. Also toggles the text on the provided buttons.
 * 
 * @param imgState 
 * @param toggleButtons 
 * @param imageDivs 
 * @param images 
 * @param imgSrcAttrName 
 * @param dispPropertyName 
 * @param dispHidePropertyName 
 * @param hideButtonText 
 * @param showButtonText 
 */ 
export function toggleImageDiv(imgState, 
                               toggleButtons, 
                               imageDivs, 
                               images, 
                               imgSrcAttrName, 
                               dispPropertyName, 
                               dispHidePropertyName, 
                               hideButtonText, 
                               showButtonText) {

    if (imgState.hidden) {

        if (!imgState.loaded) {
            images.forEach(
                (img) => img.setAttribute("src", img.getAttribute(imgSrcAttrName))
            );
            imgState.loaded = true;
        }

        imageDivs.forEach(
            (div) => div.style.display = getComputedStyle(div)
                                            .getPropertyValue(dispPropertyName)
        );

        toggleButtons.forEach((btn) => btn.textContent = hideButtonText);

    } else {

        imageDivs.forEach(
            (div) => div.style.display = getComputedStyle(div)
                                            .getPropertyValue(dispHidePropertyName)
        );

        toggleButtons.forEach((btn) => btn.textContent = showButtonText);
    }

    imgState.hidden = !imgState.hidden;
}

export function addImageDivToggleListeners(document, 
                                           thumbnailState, 
                                           toggleBtnClass,
                                           imageDivClass,
                                           imageClass,
                                           imgSrcAttrName, 
                                           dispPropertyName, 
                                           dispHidePropertyName, 
                                           hideButtonText, 
                                           showButtonText) {
    const toggleButtons = document.querySelectorAll(toggleBtnClass);
    const imageDivs = document.querySelectorAll(imageDivClass);
    const images = document.querySelectorAll(imageClass);
    toggleButtons.forEach(
        (btn) => btn.addEventListener("click", 
                                      () => toggleImageDiv(thumbnailState,
                                                           toggleButtons, 
                                                           imageDivs, 
                                                           images, 
                                                           imgSrcAttrName, 
                                                           dispPropertyName, 
                                                           dispHidePropertyName, 
                                                           hideButtonText, 
                                                           showButtonText)
                                      )
    );  
}

/**
 * 
 * @param {*} keyEvent 
 * @param {*} window 
 * @param {*} prevKeyCodes 
 * @param {*} nextKeyCodes 
 * @param {*} prevPageLink 
 * @param {*} nextPageLink 
 * @param {*} prevUpdateLink 
 * @param {*} nextUpdateLink 
 */
export function keyNavigate(keyEvent, 
                            window, 
                            prevKeyCodes, 
                            nextKeyCodes, 
                            prevPageLink, 
                            nextPageLink, 
                            prevUpdateLink, 
                            nextUpdateLink) {
    if (prevKeyCodes.has(keyEvent.key)) {
        window.location.href = (keyEvent.shiftKey ? prevUpdateLink : prevPageLink);
    } else if (nextKeyCodes.has(keyEvent.key)) {
        window.location.href = (keyEvent.shiftKey ? nextUpdateLink : nextPageLink);
    }
}

/**
 * 
 * @param {*} readPageDocument 
 * @param {*} prevPageClass 
 * @param {*} nextPageClass 
 * @param {*} prevUpdateClass 
 * @param {*} nextUpdateClass 
 * @returns 
 */
export function getNavLinks(readPageDocument, 
                            prevPageClass,
                            nextPageClass,
                            prevUpdateClass,
                            nextUpdateClass) {
    const navLinks = [];
    for (const navClass of [prevPageClass, 
                            nextPageClass, 
                            prevUpdateClass, 
                            nextUpdateClass]) {
        navLinks.push(readPageDocument.querySelector(navClass).href);
    }
    return navLinks;
}

export function getLink(document, linkClass) {
    return document.querySelector(linkClass).href;
}

class ImageSize {
    constructor(width, height, maxWindowWidth) {
        this.width = width;
        this.height = height;
        this.maxWindowWidth = maxWindowWidth;
    }
}

class ImageSizeInfo {
    constructor(imgElement) {
        this.ratio = imgElement.clientHeight / imgElement.clientWidth;
        this.srcsetStr = imgElement.getAttribute("srcset");
        this.sizesStr = imgElement.getAttribute("sizes");

        const srcsets = this.srcsetStr.split(",");
        const sizes = this.sizesStr.split(",");

        this.info = new Array(srcsets.length);

        for (let i = 0; i < srcsets.length - 1; i++) {
            const fileWidth = parseInt(srcsets[i].match(/\d+/g)
                                                 .pop());
            this.info[i] = new ImageSize(fileWidth, 
                                         fileWidth * this.ratio, 
                                         parseInt(sizes[i+1].match(/\d+/g)[0]));
        }
        
        if (!sizes[sizes.length - 1].includes("(max-width:")) {
            this.info[this.info.length - 1].maxWindowWidth = Infinity;
        }
    }
}

export function resizeImageMaps(newWindowWidth,
                                containingElement, 
                                imgSelector,
                                areaPrevSelector, 
                                areaNextSelector) {
    const sizeInfo = new ImageSizeInfo(containingElement.querySelector(imgSelector));
    const areaPrev = containingElement.querySelector(areaPrevSelector);
    
}