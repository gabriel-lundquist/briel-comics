// To keep images from loading, their initial `src` attributes are set to ""

// However, once images have been loaded once, we don't want to 
// load them again. To hide images we don't reset their `src` attributes, we 
// just set the `display` style of the thumbnails div to "none".

import { addImageDivToggleListeners } from "./briels_helpful_code.js";

// Start thumbnails as hidden, images unloaded
const thumbnailState = { hidden: Boolean(true), loaded: Boolean(false) }
addImageDivToggleListeners(thumbnailState, 
                           ".toggle-nails", 
                           ".thumbnails-div", 
                           ".thumbnail", 
                           "attr-src", 
                           "--nails-display", 
                           "--nails-hide-display", 
                           "Hide thumbnails", 
                           "Show thumbnails");
