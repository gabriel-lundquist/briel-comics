
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

export function toggleAllElementsVisibility(isHidden, elements, toggleSessionVarName = null) {
    elements.forEach((el) => el.style.display = (isHidden ? null : "none"));
    if (toggleSessionVarName) window.sessionStorage.setItem(toggleSessionVarName, isHidden ? "on" : "");
    return !isHidden;
}

export function toggleAllElementsVisibilitySelector(isHidden, selector, toggleSessionVarName = null) {
    return toggleAllElementsVisibility(isHidden, document.querySelectorAll(selector), toggleSessionVarName);
}

export function toggleElementsVisibilityWithButtons(visibilityState, 
                                                    elems, 
                                                    toggleButtons, 
                                                    hideButtonText, 
                                                    showButtonText, 
                                                    toggleSessionVarName = null) {
    toggleButtons.forEach(
        (btn) => btn.textContent = (visibilityState.isHidden ? hideButtonText : showButtonText)
    );
    visibilityState.isHidden = toggleAllElementsVisibility(visibilityState.isHidden, elems, toggleSessionVarName);
    return visibilityState.isHidden;   
}

export function toggleElementsVisibilityWithButtonsSelectors(   visibilityState, 
                                                                elemSelector, 
                                                                buttonSelector, 
                                                                hideButtonText, 
                                                                showButtonText, 
                                                                toggleSessionVarName = null ) {
    return toggleElementsVisibilityWithButtons( visibilityState, 
                                                document.querySelectorAll(elemSelector), 
                                                document.querySelectorAll(buttonSelector), 
                                                hideButtonText, 
                                                showButtonText, 
                                                toggleSessionVarName
    );
}

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
export function toggleImageDiv( imgState, 
                                toggleButtons, 
                                imageDivs, 
                                images, 
                                imgSrcAttrName, 
                                hideButtonText, 
                                showButtonText, 
                                flipSessionVar = true   ) {

    if (imgState.isHidden) {

        if (!imgState.isLoaded) {
            // images.forEach(
            //     (img) => img.setAttribute("src", img.getAttribute(imgSrcAttrName))
            // );
            imgState.isLoaded = true;
        }

    } else {
        
    }

    imgState.isHidden = flipSessionVar ? toggleElementsVisibilityWithButtons(   imgState, 
                                                                                imageDivs, 
                                                                                toggleButtons, 
                                                                                hideButtonText, 
                                                                                showButtonText, 
                                                                                "showThumbnails"    ) 
                                        : toggleElementsVisibilityWithButtons(  imgState, 
                                                                                imageDivs, 
                                                                                toggleButtons, 
                                                                                hideButtonText, 
                                                                                showButtonText  );
}

export function addElementToggleListeners(  document, 
                                            visibilityState, 
                                            toggleBtnSelector, 
                                            elemSelector, 
                                            hideButtonText, 
                                            showButtonText,  
                                            toggleSessionVarName = null ) {
    
    const elems = document.querySelectorAll(elemSelector);
    const buttons = document.querySelectorAll(toggleBtnSelector);
    if (visibilityState.isHidden) {
        toggleElementsVisibilityWithButtons(visibilityState, 
                                            elems, 
                                            buttons, 
                                            hideButtonText, 
                                            showButtonText, 
                                            toggleSessionVarName);
    }
    buttons.forEach((btn) => btn.addEventListener(  
            "click", 
            () => visibilityState.isHidden = toggleElementsVisibilityWithButtons(   visibilityState, 
                                                                                    elems, 
                                                                                    buttons, 
                                                                                    hideButtonText, 
                                                                                    showButtonText, 
                                                                                    toggleSessionVarName    )
    ));
}

export function addImageDivToggleListeners( document, 
                                            thumbnailState, 
                                            toggleBtnClass,
                                            imageDivClass,
                                            imageClass,
                                            imgSrcAttrName, 
                                            hideButtonText, 
                                            showButtonText  ) {
    const toggleButtons = document.querySelectorAll(toggleBtnClass);
    const imageDivs = document.querySelectorAll(imageDivClass);
    const images = document.querySelectorAll(imageClass);
    // if (thumbnailState.isHidden) {
    //     toggleImageDiv( thumbnailState,
    //                     toggleButtons, 
    //                     imageDivs, 
    //                     images, 
    //                     imgSrcAttrName, 
    //                     hideButtonText, 
    //                     showButtonText  );
    // }
    toggleButtons.forEach(
        (btn) => btn.addEventListener(  "click", 
                                        function() {
                                            toggleImageDiv( thumbnailState,
                                                            toggleButtons, 
                                                            imageDivs, 
                                                            images, 
                                                            imgSrcAttrName, 
                                                            hideButtonText, 
                                                            showButtonText  );
                                        } 
                                    )
    );  
}

export function noneHaveFocus(selectors) {
    return !selectors.some(
            (selector) => Array.from(document.querySelectorAll(selector)).some(
                    (elem) => elem == document.activeElement 
                            || elem.contains(document.activeElement)
                )
            )
}

export function keyNavigate(keyEvent, 
                            window, 
                            prevKeys, 
                            nextKeys, 
                            prevPageLink, 
                            nextPageLink, 
                            prevUpdateLink, 
                            nextUpdateLink, 
                            noNavWhenFocusedSelectors) {
    if (noneHaveFocus(noNavWhenFocusedSelectors)) {
        if (prevKeys.has(keyEvent.key)) {
            if (keyEvent.shiftKey) {
                if (prevUpdateLink) window.location.href = prevUpdateLink;
            } else {
                if (prevPageLink) window.location.href = prevPageLink;
            }
        } else if (nextKeys.has(keyEvent.key)) {
            if (keyEvent.shiftKey) {
                if (nextUpdateLink) window.location.href = nextUpdateLink;
            } else {
                if (nextPageLink) window.location.href = nextPageLink;
            }
        }
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

export function getLink(element, linkClass) {
    const linkElement = element.querySelector(linkClass);
    return linkElement ? linkElement.href : null;
}

export function resizeNavImageMaps( imgElement, 
                                    areaPrevElement, 
                                    areaNextElement,
                                    useNaturalDimensions = true ) {
    const imgWidth = useNaturalDimensions ? imgElement.naturalWidth : imgElement.clientWidth;
    const imgHeight = useNaturalDimensions ? imgElement.naturalHeight : imgElement.clientHeight;
    if (areaPrevElement)
        areaPrevElement.setAttribute("coords", 
                        `0,0,${Math.ceil(0.25 * imgWidth)},${imgHeight}`);
    if (areaNextElement)
        areaNextElement.setAttribute("coords", 
                        `${Math.ceil(0.75 * imgWidth)},0,${imgWidth},${imgHeight}`);
}

// export function toggleCollapsible()