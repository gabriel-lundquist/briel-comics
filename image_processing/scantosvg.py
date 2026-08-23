import vtracer
from PIL import Image
import sys
import os

def convert_files(paths: tuple):
    for path in paths:
        [name, ext] = os.path.splitext(path)
        if os.path.isfile(path) and str.lower(ext) in [".jpg", ".jpeg", ".png"]:
            new_path = name + ".svg"
            vtracer.convert_image_to_svg_py(
                    path, 
                    new_path,  
                    filter_speckle=min(128, Image.open(path).size[0] // 50)
            )
            print("Converted " + os.path.basename(path) + " to svg at " + new_path)

if len(sys.argv) == 2 and os.path.isdir(sys.argv[1]):
    convert_files(os.listdir(sys.argv[1]))

else: convert_files(sys.argv[1:])

