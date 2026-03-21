import windows_metadata as wm
import csv
from datetime import datetime as dt
import os

def even_preceding_backslashes(prefix_text):
    # Count preceding backslashes
    backslash_count = 0
    index = len(prefix_text) - 1
    while index >= 0 and prefix_text[index] == '\\': 
        backslash_count += 1
        index -= 1
    return backslash_count % 2 == 0

def sanitize_unescaped_quotes(text):
    """
    Replaces unescaped quotes with escaped quotes.
    """
    dequoted_fragments = text.split('"')

    for frag_index, fragment in enumerate(dequoted_fragments[:-1]):
        # If odd number of backslashes, remove final backslash...
        if not even_preceding_backslashes(fragment):
            dequoted_fragments[frag_index] = fragment[:-1]

    # ...because we add it back in, here.
    return '\\"'.join(dequoted_fragments)

def convert_timestamp(timestamp_str):
    """
    Converts a timestamp like "M/DD/YYYY 00:00 PM" 
    to "YYYY-MM-DD hh:mm:ss".
    """
    return dt.strptime(timestamp_str, "%m/%d/%Y %I:%M %p") \
             .strftime("%Y-%m-%d %H:%M:%S")

def remove_nondigits(text):
    return ''.join([char for char in text if char.isdigit()])

def strip_period_from_file_extension(extension):
    return extension[extension.rfind('.')+1 : ]

def sanitize_newlines_and_tabs(text):
    return text.replace('\t', ' ')  \
               .replace('\n', ' ')  \
               .replace('\r', '')

def sanitize_fancy_quotes(text):
    return text.replace('“', '"')   \
               .replace('”', '"')   \
               .replace('’', "'")   \
               .replace('‘', "'")

def convert_to_size_in_kb(size_metadata):
    size = float(''.join([char for char in size_metadata 
                          if char.isdigit() or char == '.']))
    # Last two characters of the windows metadata size field
    # is the units of memory
    size_unit = size_metadata[-2:]

    if size_unit == "KB":
        pass
    elif size_unit == "MB":
        size *= 1000
    elif size_unit == "GB":
        size *= 1000_000
    elif size_unit == "TB":
        size *= 1000_000_000
    else:
        raise ValueError(f"Jesus. What did you even give me. "
                         + "It was {size_unit}")
    
    return round(size)

def sanitize_path_backslashes(path):
    return path.replace('\\', '/')

def get_alttext(alttext_filepath, 
                file_encoding='locale'):
    alttext_str = ''
    with open(alttext_filepath, 
              mode='r', 
              encoding=file_encoding) as alttext_file:
        alttext_str += alttext_file.read()
    return sanitize_newlines_and_tabs(
                sanitize_fancy_quotes(alttext_str)
            )

def register_sql_dialect():
    csv.register_dialect('sql', 
                         delimiter='\t', 
                         doublequote=False, 
                         escapechar='\\', 
                         lineterminator='\n', 
                         quotechar=None, 
                         quoting=csv.QUOTE_NONE, 
                         skipinitialspace=False, 
                         strict=False)

def which_aspectratio(ratio_str, 
                      aspectratiolist_filepath):
    if ratio_str == '\\N':
        return ratio_str

    if 'sql' not in csv.list_dialects():
        register_sql_dialect()
    
    with open(aspectratiolist_filepath, 'r') as ratiolist_file:
        ratioreader = csv.reader(ratiolist_file, dialect='sql')
        for row in ratioreader:
            if ratio_str == row[0]:
                return row[1]
        else:
            raise ValueError(f"{ratio_str} not in the list of aspect ratios")

def get_file_record(img_filepath, 
                    aspectratiolist_filepath, 
                    alttext_filepath=None, 
                    ratio_str=None):
    '''
    Each record in the File table consists of the following attributes, 
    in this order (as of July 29th 2025):
        File ID (automatically created by MySQL server when \\N is entered)
        Location
        Width in pixels
        Height in pixels
        Alt text
        Upload date (created by MySQL when no value is given)
        Modify date (created by MySQL when no value is given)
        File extension
        File size in kilobytes
        Associated page ID (okay to have a null value if no page exists yet)
        Aspect ratio (an integer ID)

    We DON'T enter values for Upload date and Modify date, since 
    those are timestamps with no default behavior when a null value
    is entered.
    '''

    attr_dict = wm.WindowsAttributes(img_filepath).get_attribute_dict()

    prompt_prefix = f"{os.path.basename(img_filepath)}:\n"

    alttext = ''
    if alttext_filepath is not None:
        alttext = get_alttext(alttext_filepath)
    else:
        input_str = input(prompt_prefix + "Enter alt text.\n> ")
        if os.path.exists(input_str):
            alttext = get_alttext(input_str)
        else:
            alttext = sanitize_fancy_quotes(
                            sanitize_newlines_and_tabs(input_str)
                        )

    if ratio_str is None:
        ratio_str = input(prompt_prefix + "Enter aspect ratio.\n> ")

    return ['\\N',
            sanitize_path_backslashes(attr_dict['Path']), 
            remove_nondigits(attr_dict['Width']), 
            remove_nondigits(attr_dict['Height']), 
            alttext,
            strip_period_from_file_extension(attr_dict['File extension']), 
            str(convert_to_size_in_kb(attr_dict['Size'])), 
            '\\N', 
            which_aspectratio(ratio_str, aspectratiolist_filepath)
           ]

def write_records_sql_format(filepath, 
                             records, 
                             write_mode='a+'):
    with open(filepath, write_mode, newline='\n') as record_file:
        for record in records:
            record_file.write('\t'.join(record) + '\n')

def is_image(direntry):
    return (direntry.is_file()
            and (direntry.name[-4:].lower() == '.png' 
                or direntry.name[-4:].lower() == '.jpg'
                or direntry.name[-5:].lower() == '.jpeg'
                or direntry.name[-4:].lower() == '.gif'
                or direntry.name[-5:].lower() == '.tiff'
                or direntry.name[-4:].lower() == '.tif'
            )
        )

def get_dir_file_records(dir_path, 
                         alttext_path=None, 
                         aspectratio_filepath=('C:\\ProgramData\\MySQL'
                                               +'\\MySQL Server 8.0'
                                               +'\\Uploads'
                                               +'\\aspect_ratio_list.txt')):
    # os.chdir(dir_path)
    direntry_list = []
    with os.scandir(dir_path) as dir:
        for entry in dir:
            direntry_list.append(entry)

    records = []
    for entry in direntry_list:
        if is_image(entry):
            print(f"\nGetting record for file {entry.name}...")
            records.append(get_file_record(os.path.abspath(entry.path),
                                           aspectratio_filepath, 
                                           alttext_filepath=alttext_path))

    return records


    