import windows_metadata as wm
import csv
from datetime import datetime as dt

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
    return extension[extension.rfind('.'):]

def newline_to_space(text):
    return text.replace('\n', ' ') \
               .replace('\r', ' ')

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
        raise ValueError("Jesus. What did you even give me.")
    
    return round(size)

def write_records_SQL_format(filepath, 
                             records, 
                             write_mode='w'):
    with open(filepath, write_mode, newline='\n') as record_file:
        record_writer = csv.writer(record_file, 
                                   delimiter='\t', 
                                   escapechar='\\', 
                                   doublequote=False, 
                                   lineterminator='\n', 
                                   quoting=csv.QUOTE_MINIMAL)
        record_writer.writerows(records)

# def sanitize_special_unicode(text):
#     unspecial_fragments = text.split("\\u")

#     for index, fragment in enumerate(unspecial_fragments[1:]):
#         # If there are an even number of preceding backslashes, 
#         # the following four characters are in fact a unicode 
#         # special character, since the \ in \u isn't escaped.
#         if even_preceding_backslashes(unspecial_fragments[index-1]):
#             unspecial_fragments[index] = fragment[4:]

#     return ''.join(unspecial_fragments)