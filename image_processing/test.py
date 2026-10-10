import json
from pathlib import Path

my_path = Path('./draft_site/images/Tome_Does_Research/p1/nails/metadata.json')
my_path.touch()
with open(my_path, mode='w', encoding='utf_8') as fp:
    json.dump(  {   "alttext": "A small, black-and-white thumbnail image for Tome Does Research page 1.", 
                    "ratio": "1:1", 
                    "purpose": "thumbnail"  }, 
                fp)