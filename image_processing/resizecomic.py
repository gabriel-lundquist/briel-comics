import cv2 as cv
import os
import argparse
import pathlib

image_exts = (".png", ".jpg", ".jpeg", ".tiff")
default_pixel_ratios = (1, 1.25, 1.5, 1.75, 2, 3)
default_display_widths = (800, 1400, 2100)
default_nail_widths = (100, 200)
default_in_dir = "."

def calc_widths_by_ratios(display_width:tuple|int=default_display_widths, 
                          pixel_ratio:tuple|float|int=default_pixel_ratios):
    if isinstance(display_width, int):
        if isinstance(pixel_ratio, (int, float)):
            return display_width * pixel_ratio
        else:
            return {int(ratio * display_width) for ratio in pixel_ratio}
    else:
        if isinstance(pixel_ratio, (int, float)):
            return {int(pixel_ratio * width) for width in display_width}
        else:
            return {int(ratio * width)  for width in display_width 
                                        for ratio in pixel_ratio}

default_widths = calc_widths_by_ratios()

def downscale_to_width(img, new_width:int, old_width:int, old_height:int):
    
    if (old_width / new_width >= 2):
        # blur before downscaling to reduce jagged edges
        width_factor = int(old_width / new_width)
        # kernel width must be odd
        kwidth = max(3, width_factor + (1 - (width_factor % 2))) 
        img = cv.GaussianBlur(img, (kwidth, kwidth), 0)

    return cv.resize(img, 
                     (new_width, (old_height * new_width) // old_width), 
                     cv.INTER_AREA)

def write_resized_img(img, 
                      new_width:int, 
                      output_dir:os.PathLike = None, 
                      output_name:str = None, 
                      output_ext:str=".png", 
                      output_path:os.PathLike = None,
                      is_nail:bool=False):
    path = ''
    if output_path is None:
        path = os.path.join(output_dir, 
                            f"{output_name}_{new_width}w" 
                                    + ("_nail" if is_nail else "") 
                                    + output_ext)
    else:
        path = output_path

    if output_ext == ".png":
        val = cv.imwrite(path, img, [cv.IMWRITE_PNG_COMPRESSION, 9])
    else:
        val = cv.imwrite(path, img)
    print("Wrote to " + path)

def write_downscale(img, 
                    new_width:int, 
                    old_width:int, 
                    old_height:int,
                    output_dir:os.PathLike, 
                    output_name:str, 
                    output_ext:str=".png", 
                    is_nail:bool=False):
    return write_resized_img(downscale_to_width(img, new_width, old_width, old_height), 
                             new_width=new_width, 
                             output_dir=output_dir, 
                             output_name=output_name, 
                             output_ext=output_ext, 
                             is_nail=is_nail)

def resize_comics(path:os.PathLike, 
                  output_dir:os.PathLike, 
                  output_name:str, 
                  output_ext:str=".png", 
                  output_nail_ext:str=".jpg", 
                  output_widths=default_widths, 
                  output_nail_widths=default_nail_widths):
    img = cv.imread(path)
    assert img is not None, path + " could not be read."

    height, width = img.shape[:2]
    for new_width in output_widths:
        if new_width <= width:
            write_downscale(img, 
                            new_width, 
                            width, 
                            height, 
                            output_dir, 
                            output_name, 
                            output_ext=output_ext)
        else: print(f"Didn't downscale to {new_width}px since {os.path.basename(path)} "
                    + f"is {width}px wide")

    # TODO: condition for landscape images
    for nail_width in output_nail_widths:
        nail_img = downscale_to_width(img, nail_width, width, height)
        nail_img_crop = nail_img[0:nail_width, :] # height, then width
        write_resized_img(nail_img_crop, 
                          new_width=nail_width, 
                          output_dir=output_dir, 
                          output_name=output_name, 
                          output_ext=output_nail_ext, 
                          is_nail=True)

def resize_folder(input_dir:os.PathLike, 
                  output_dir:os.PathLike, 
                  output_ext:str=".png", 
                  output_widths:tuple=default_widths, 
                  output_nail_ext:str=".jpg", 
                  output_nail_widths:tuple=default_nail_widths):
    with os.scandir(input_dir) as direntries:
        for entry in direntries:
            [baseroot, ext] = os.path.splitext(entry.name)
            if entry.is_file() and ext in image_exts:
                resize_comics(entry.path, 
                              output_dir, 
                              baseroot, 
                              output_ext=output_ext, 
                              output_widths=output_widths, 
                              output_nail_ext=output_nail_ext, 
                              output_nail_widths=output_nail_widths
                )

def unquote(s:str):
    return s.strip("'\"\\")

parser = argparse.ArgumentParser()
parser.add_argument("-d", "--indir", default=default_in_dir, type=str)
parser.add_argument("-D", "--outdir", default=default_in_dir, type=str)
parser.add_argument("-f", "--infile", action="append", default=[], type=pathlib.Path)
parser.add_argument("-n", "--outname", action="append", default=[], type=str)
parser.add_argument("-w", "--width", action="append", type=int)
parser.add_argument("-W", "--nailwidth", action="append", type=int)
parser.add_argument("-e", "--extension", default=".png", type=str)
parser.add_argument("-s", "--spread", choices=["normal", "double"], default="normal")

args = parser.parse_args()
widths = args.width if args.width is not None else default_widths
nailwidths = args.nailwidth if args.nailwidth is not None else default_nail_widths
in_dir_path = pathlib.Path(unquote(args.indir))
out_dir_path = pathlib.Path(unquote(args.outdir))

if args.infile is None or args.indir != default_in_dir:
    resize_folder(in_dir_path, out_dir_path, args.extension, widths, nailwidths)

for infile, outname in zip(args.infile, args.outname):
    resize_comics(  infile, 
                    args.outdir, 
                    outname, 
                    output_ext=args.extension, 
                    output_widths=widths, 
                    output_nail_widths=nailwidths  )
for infile in args.infile[len(args.outname):]:
    outname = os.path.splitext(os.path.basename(infile))[0]
    resize_comics(  infile, 
                    args.outdir, 
                    outname, 
                    output_ext=args.extension, 
                    output_widths=widths, 
                    output_nail_widths=nailwidths  )
