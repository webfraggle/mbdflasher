
# Activate on Mac
source ./venv/bin/activate

# Activate on Windows

einmalig 
set-executionpolicy remotesigned

.\venv\Scripts\Activate.ps1

pip install -r .\requirements.txt


Build:
pyinstaller .\build-on-win.spec

# Brew URLs
https://www.brewflasher.com/firmware/api/project_list/all/
https://www.brewflasher.com/firmware/api/firmware_family_list/
https://www.brewflasher.com/firmware/api/firmware_list/all/

https://www.brewflasher.com/firmware//api/flash_verify/ <- POST

request_dict = {
            'firmware_id': self.id,
            'flasher': flasher,
            'flasher_version': brewflasher_version
        }
        url = BREWFLASHER_COM_URL + "/api/flash_verify/"
        r = requests.post(url, json=request_dict)



# ZZA partitions

Parsing binary partition input...
Verifying table...
# ESP-IDF Partition Table
# Name, Type, SubType, Offset, Size, Flags
nvs,data,nvs,0x9000,20K,
otadata,data,ota,0xe000,8K,
app0,app,ota_0,0x10000,1280K,
app1,app,ota_1,0x150000,1280K,
spiffs,data,spiffs,0x290000,1408K,
coredump,data,coredump,0x3f0000,64K,