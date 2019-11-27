# our_alma
Scripts to update OUR license links in Alma e-collections

# Requirements:
* Alma API key with Electronic Read/Write and Configuration Read-only permissions.
* CSV extract from SFX with the following fields in exact order: TARGET_SERVICE_ID,GENERAL_NOTE,TARGET_ID,TARGET_NAME. York's CSV file is included as an example. See: york_targets_with_notes_serviceID_names.csv
* PHP 5.4 or higher with JSON and CURL modules
* CURL 

# Usage:
```
git clone https://github.com/yorkulibraries/our_alma.git
cd our_alma
php extract_json.php API_KEY /path/to/sfx/collection_id_link_id.csv
```

After running the above command, the original Electronic Collection records and the updated records are written to output/API_KEY directory as JSON encoded text files. A file containing all the CURL commands (update_cmd.txt) to PUT the updated records back into Alma is also created. You can feed update_cmd.txt to BASH on linux, or rename it to .BAT and run it on Windows.

```
cd output/API_KEY
sh update_cmd.txt
```
