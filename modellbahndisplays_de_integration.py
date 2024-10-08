from dataclasses import dataclass, field
import os.path
from typing import Dict, List
import requests
import copy
import sys
import fhash


BREWFLASHER_COM_URL = "https://www.modellbahn-displays.de/firmware"
MODEL_VERSION = 3


@dataclass
class DeviceFamily:
    name: str = ""
    flash_method: str = ""
    detection_family: str = ""
    id: int = 0
    firmware: List['Firmware'] = field(default_factory=list)
    use_1200_bps_touch: bool = False
    download_url_bootloader: str = ""
    download_url_otadata: str = ""
    otadata_address: str = ""
    checksum_bootloader: str = ""
    checksum_otadata: str = ""

    def __str__(self):
        return self.name


@dataclass
class Project:
    name: str = ""
    weight: int = 0
    description: str = ""
    support_url: str = ""
    id: int = 0
    project_url: str = ""
    documentation_url: str = ""
    show: str = ""  # TODO - Ditch this entirely
    device_families: Dict[int, DeviceFamily] = field(default_factory=dict)

    def __str__(self):
        return self.name


@dataclass
class Firmware:
    name: str = ""
    version: str = ""
    family_id: int = 0
    family: DeviceFamily = None
    variant: str = ""
    is_fermentrack_supported: str = ""
    in_error: str = ""
    description: str = ""
    variant_description: str = ""
    download_url: str = ""
    post_install_instructions: str = ""
    weight: str = ""
    download_url_partitions: str = ""
    download_url_spiffs: str = ""
    checksum: str = ""
    checksum_partitions: str = ""
    checksum_spiffs: str = ""
    spiffs_address: str = ""
    id: int = 0
    project_id: int = 0

    def __str__(self):
        str_rep = self.name

        if len(self.version) > 0:
            str_rep += " - {}".format(self.version)
        if len(self.variant) > 0:
            str_rep += " - {}".format(self.variant)
        return str_rep

    @classmethod
    def download_file(cls, full_path, url, checksum, check_checksum, force_download):
        if os.path.isfile(full_path):
            if force_download:  # If we're just going to force the download anyways, just kill the file
                os.remove(full_path)
            elif checksum == fhash.hash_of_file(full_path):  # If the file already exists check the checksum
                # The file is valid - return the path
                return True
            else:
                # The checksum check failed - Kill the file
                os.remove(full_path)

        if len(url) < 12:  # If we don't have a URL, we can't download anything
            return False

        # So either we don't have a downloaded copy (or it's invalid). Let's download a new one.
        r = requests.get(url, stream=True)

        with open(full_path, str("wb")) as f:
            for chunk in r.iter_content():
                f.write(chunk)

        # Now, let's check that the file is valid (but only if check_checksum is true)
        if check_checksum:
            if os.path.isfile(full_path):
                # If the file already exists check the checksum (and delete if it fails)
                if checksum != fhash.hash_of_file(full_path):
                    os.remove(full_path)
                    return False
            else:
                return False
        # The file is valid (or we aren't checking checksums). Return the path.
        return True

    def full_filepath(self, bintype: str):
        if getattr(sys, 'frozen', False):
            cur_filepath = os.path.dirname(os.path.realpath(sys._MEIPASS))
        else:
            cur_filepath = os.path.dirname(os.path.realpath(__file__))
        return os.path.join(cur_filepath, bintype + ".bin")

    def download_to_file(self, check_checksum: bool = True, force_download: bool = False):
        # If this is a multipart firmware (e.g. ESP32, with partitions or SPIFFS) then download the additional parts.
        if len(self.download_url_partitions) > 12:
            print("Downloading partitions file...")
            if not self.download_file(self.full_filepath("partitions"), self.download_url_partitions,
                                      self.checksum_partitions, check_checksum, force_download):
                print("Error downloading partitions file!")
                return False

        if len(self.download_url_spiffs) > 12 and len(self.spiffs_address) > 2:
            print("Downloading SPIFFS/LittleFS file...")
            if not self.download_file(self.full_filepath("spiffs"), self.download_url_spiffs,
                                      self.checksum_spiffs, check_checksum, force_download):
                print("Error downloading SPIFFS/LittleFS file!")
                return False

        if len(self.family.download_url_bootloader) > 12:
            print("Downloading bootloader file...")
            if not self.download_file(self.full_filepath("bootloader"), self.family.download_url_bootloader,
                                      self.family.checksum_bootloader, check_checksum, force_download):
                print("Error downloading bootloader file!")
                return False

        if len(self.family.download_url_otadata) > 12 and len(self.family.otadata_address) > 2:
            print("Downloading otadata file...")
            if not self.download_file(self.full_filepath("otadata"), self.family.download_url_otadata,
                                      self.family.checksum_otadata, check_checksum, force_download):
                print("Error downloading otadata file!")
                return False

        # Always download the main firmware
        print("Downloading main firmware file...")
        return self.download_file(self.full_filepath("firmware"), self.download_url, self.checksum, check_checksum,
                                  force_download)

    def pre_flash_web_verify(self, brewflasher_version, flasher="BrewFlasher"):
        """Recheck that the checksum we have cached is still the one that brewflasher.com reports"""
        request_dict = {
            'firmware_id': self.id,
            'flasher': flasher,
            'flasher_version': brewflasher_version
        }
        url = BREWFLASHER_COM_URL + "/api/flash_verify/"
        r = requests.post(url, json=request_dict)
        # print(r.text)
        # print(self.checksum)
        response = r.json()
        if response['status'] == "success":
            if response['message'] == self.checksum:
                # print("YO")
                return True
        # print("NOOOO")
        return False

    def remove_downloaded_firmware(self):
        """Delete the downloaded firmware files"""

        firmware_types = ["bootloader", "firmware", "partitions", "spiffs", "otadata"]

        for firmware_type in firmware_types:
            if os.path.exists(self.full_filepath(firmware_type)):
                os.remove(self.full_filepath(firmware_type))


@dataclass
class FirmwareList:
    DeviceFamilies: Dict[int, DeviceFamily] = field(default_factory=dict)
    Projects: Dict[int, Project] = field(default_factory=dict)
    # TODO - Double check valid_family_ids now that we support Arduino here
    valid_family_ids: List[int] = field(default_factory=list)

    def __str__(self):
        return "Device Families"

    def load_projects_from_website(self) -> bool:
        url = BREWFLASHER_COM_URL + "/api/project_list/all/"
        response = requests.get(url)
        data = response.json()

        if len(data) > 0:
            for row in data:
                try:
                    # This gets wrapped in a try/except as I don't want this failing if the local copy of BrewFlasher
                    # is slightly behind what is available at Brewflasher.com (eg - if there are new device families)
                    new_project = Project(name=row['name'], weight=row['weight'], id=row['id'],
                                          description=row['description'], support_url=row['support_url'],
                                          project_url=row['project_url'], documentation_url=row['documentation_url'],
                                          show=row['show_in_standalone_flasher'])
                    self.Projects[row['id']] = copy.deepcopy(new_project)
                except:
                    print("\nUnable to load projects from BrewFlasher.com.")
                    print("Please check your internet connection and try launching BrewFlasher again.\nIf you continue "
                          "to receive this error, please check that you have the latest version of BrewFlasher.")
                    pass

            return True
        return False  # We didn't get data back from Brewflasher.com, or there was an error

    def load_families_from_website(self, load_esptool_only: bool = True) -> bool:
        try:
            url = BREWFLASHER_COM_URL + "/api/firmware_family_list/"
            response = requests.get(url)
            data = response.json()
        except:
            return False

        if len(data) > 0:
            for row in data:
                try:
                    # This gets wrapped in a try/except as I don't want this failing if the local copy of BrewFlasher
                    # is slightly behind what is available at Brewflasher.com (eg - if there are new device families)
                    new_family = DeviceFamily(name=row['name'], flash_method=row['flash_method'], id=row['id'],
                                              detection_family=row['detection_family'],
                                              download_url_bootloader=row['download_url_bootloader'],
                                              download_url_otadata=row['download_url_otadata'],
                                              otadata_address=row['otadata_address'],
                                              checksum_bootloader=row['checksum_bootloader'],
                                              checksum_otadata=row['checksum_otadata'],
                                              use_1200_bps_touch=row['use_1200_bps_touch'])
                    if new_family.flash_method != "esptool" and load_esptool_only:
                        continue  # Only save families that use esptool if this is BrewFlasher Desktop
                    self.DeviceFamilies[new_family.id] = copy.deepcopy(new_family)
                    self.valid_family_ids.append(new_family.id)
                    for this_project in self.Projects:
                        self.Projects[this_project].device_families[new_family.id] = copy.deepcopy(new_family)
                except:
                    print("\nUnable to load device families from BrewFlasher.com.")
                    print("Please check your internet connection and try launching BrewFlasher again.\nIf you continue "
                          "to receive this error, please check that you have the latest version of BrewFlasher.")
                    pass

            return True
        return False  # We didn't get data back from Brewflasher.com, or there was an error

    def load_firmware_from_website(self) -> bool:
        # This is intended to be run after load_families_from_website
        try:
            url = BREWFLASHER_COM_URL + "/api/firmware_list/all/"
            response = requests.get(url)
            data = response.json()
        except:
            return False

        if len(data) > 0:
            # Then loop through the data we received and recreate it again
            for row in data:
                if row['family_id'] not in self.valid_family_ids:
                    continue  # The family ID has been excluded (e.g. Arduino, and esptool only is selected)

                new_firmware = Firmware(
                    name=row['name'], version=row['version'], family_id=row['family_id'],
                    family=self.DeviceFamilies[row['family_id']],
                    variant=row['variant'], is_fermentrack_supported=row['is_fermentrack_supported'],
                    in_error=row['in_error'], description=row['description'],
                    variant_description=row['variant_description'], download_url=row['download_url'],
                    post_install_instructions=row['post_install_instructions'], weight=row['weight'],
                    download_url_partitions=row['download_url_partitions'],
                    download_url_spiffs=row['download_url_spiffs'], checksum=row['checksum'],
                    checksum_partitions=row['checksum_partitions'], checksum_spiffs=row['checksum_spiffs'],
                    spiffs_address=row['spiffs_address'], project_id=row['project_id'], id=row['id'],
                )

                # Add the firmware to the appropriate DeviceFamily's list
                self.DeviceFamilies[new_firmware.family_id].firmware.append(new_firmware)
                self.Projects[new_firmware.project_id].device_families[new_firmware.family_id].firmware.append(
                    new_firmware)

            return True  # Firmware table is updated
        return False  # We didn't get data back from Brewflasher.com, or there was an error

    def cleanse_projects(self):
        for this_project_id in list(self.Projects):
            this_project = self.Projects[this_project_id]
            for this_device_family_id in list(
                    this_project.device_families):  # Iterate the list as we're deleting members
                this_device_family = this_project.device_families[this_device_family_id]
                if len(this_device_family.firmware) <= 0:
                    # If there aren't any firmware instances in this device family, delete it
                    del this_project.device_families[this_device_family_id]

            # Once we've run through and cleaned up the device family list for this project, check if anything remains
            if len(this_project.device_families) <= 0:
                # If there are no remaining device families, then there isn't any (flashable) firmware for this project.
                # Delete the project.
                del self.Projects[this_project_id]

    # We need to load everything in a specific order for it to work
    def load_from_website(self, load_esptool_only: bool = True) -> bool:
        if self.load_projects_from_website():
            if self.load_families_from_website(load_esptool_only):
                if self.load_firmware_from_website():
                    self.cleanse_projects()
                    return True
        return False

    def get_project_list(self):
        available_projects = []
        for this_project_id in self.Projects:
            available_projects.append(str(self.Projects[this_project_id]))
        if len(available_projects) == 0:
            available_projects = ["Unable to download project list"]
        return available_projects

    def get_project_id(self, project_str) -> int or None:
        # Returns the id in self.Projects for the project with the selected name, or returns None if it cannot be found
        for this_project_id in self.Projects:
            if str(self.Projects[this_project_id]) == project_str:
                return this_project_id
        return None

    def get_device_family_id(self, project_id, device_family_str) -> int or None:
        # Returns the id in self.Projects for the project with the selected name, or returns None if it cannot be found
        if project_id not in self.Projects:
            # The project_id was invalid - Return None
            return None

        for this_family_id in self.Projects[project_id].device_families:
            if str(self.Projects[project_id].device_families[this_family_id]) == device_family_str:
                return this_family_id
        return None

    def get_device_family_list(self, selected_project_id=None):
        if selected_project_id is None:  # We weren't given a project_id - return a blank list
            return [""]
        if selected_project_id not in self.Projects:  # The project_id was invalid  - return a blank list
            return [""]
        # Iterate over the list of device_families to populate the list
        available_devices = []
        for this_family_id in self.Projects[selected_project_id].device_families:
            available_devices.append(str(self.Projects[selected_project_id].device_families[this_family_id]))
        if len(available_devices) == 0:
            available_devices = ["Unable to download device family list"]
        return available_devices

    def get_firmware_list(self, selected_project_id=None, selected_family_id=None):
        if selected_project_id is None:  # We weren't given a project_id - return a blank list
            return [""]
        if selected_project_id not in self.Projects:  # The project_id was invalid  - return a blank list
            return [""]
        if selected_family_id is None:  # We weren't given a project_id - return a blank list
            return [""]
        if selected_family_id not in self.Projects[selected_project_id].device_families:  # The family_id was invalid  - return a blank list
            return [""]
        # Iterate over the list of device_families to populate the list
        available_firmware = []
        for this_firmware in self.Projects[selected_project_id].device_families[selected_family_id].firmware:
            available_firmware.append(str(this_firmware))
        if len(available_firmware) == 0:
            available_firmware = ["Unable to download firmware list"]
        return available_firmware

    def get_firmware(self, project_id, family_id, firmware_str) -> Firmware or None:
        # Returns the id in self.Projects for the project with the selected name, or returns None if it cannot be found
        if project_id not in self.Projects:
            # The project_id was invalid - Return None
            return None
        if family_id not in self.Projects[project_id].device_families:
            # The family_id was invalid  - Return None
            return None
        # Iterate through the list of firmware to find the appropriate one
        for this_firmware in self.Projects[project_id].device_families[family_id].firmware:
            if str(this_firmware) == firmware_str:
                return this_firmware
        return None


if __name__ == "__main__":
    import pprint

    firmware_list = FirmwareList()
    firmware_list.load_from_website()

    pprint.pprint(firmware_list)
