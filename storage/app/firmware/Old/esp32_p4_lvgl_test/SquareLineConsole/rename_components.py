#!/usr/bin/env python3
"""
SquareLine Studio Component Renamer

This script reads a SquareLine .spj project file and renames all component
codenames to follow a consistent naming convention:
    [ScreenName]_[WidgetType]_[ObjectName]

Usage:
    python rename_components.py [input.spj] [output.spj]

If no output file is specified, it will create a backup and modify in place.
"""

import json
import sys
import shutil
from datetime import datetime
from pathlib import Path

# Widget type detection from strtype patterns
WIDGET_STRTYPE_MAP = {
    'TEXTAREA/': 'Textarea',
    'BUTTON/': 'Button',
    'LABEL/': 'Label',
    'IMAGE/': 'Image',
    'SWITCH/': 'Switch',
    'SLIDER/': 'Slider',
    'DROPDOWN/': 'Dropdown',
    'ROLLER/': 'Roller',
    'CHECKBOX/': 'Checkbox',
    'ARC/': 'Arc',
    'BAR/': 'Bar',
    'SPINNER/': 'Spinner',
    'KEYBOARD/': 'Keyboard',
    'CHART/': 'Chart',
    'TABLE/': 'Table',
    'CALENDAR/': 'Calendar',
    'COLORWHEEL/': 'ColorWheel',
    'LED/': 'LED',
    'LINE/': 'Line',
    'LIST/': 'List',
    'MENU/': 'Menu',
    'METER/': 'Meter',
    'SCALE/': 'Scale',
    'SPINBOX/': 'Spinbox',
    'TABVIEW/': 'TabView',
    'TILEVIEW/': 'TileView',
    'BUTTONMATRIX/': 'ButtonMatrix',
    'CANVAS/': 'Canvas',
    'MSGBOX/': 'MsgBox',
    'SPANGROUP/': 'SpanGroup',
    'WINDOW/': 'Window',
    'IMAGEBUTTON/': 'ImageButton',
}

def detect_widget_type(properties):
    """Detect widget type from properties strtype values."""
    for prop in properties:
        strtype = prop.get('strtype', '')
        for prefix, widget_type in WIDGET_STRTYPE_MAP.items():
            if strtype.startswith(prefix):
                return widget_type
    return 'Container'  # Default for panels/containers

def find_property(properties, strtype):
    """Find a property by its strtype."""
    for prop in properties:
        if prop.get('strtype') == strtype:
            return prop
    return None

def get_codename_property(properties):
    """Get the codename property (nested in OBJECT/Name childs)."""
    name_prop = find_property(properties, 'OBJECT/Name')
    if name_prop and 'childs' in name_prop:
        for child in name_prop['childs']:
            if child.get('strtype') == '_codename/Codename':
                return child
    return None

def process_object(obj, screen_name=None, stats=None):
    """
    Process an object and its children, updating codenames.
    """
    if stats is None:
        stats = {'renamed': 0, 'screens': 0, 'total': 0}

    properties = obj.get('properties', [])
    name_prop = find_property(properties, 'OBJECT/Name')

    if not name_prop:
        # Process children anyway
        for child in obj.get('children', []):
            process_object(child, screen_name, stats)
        return None

    obj_name = name_prop.get('strval', '')
    stats['total'] += 1

    # Detect widget type from properties
    widget_type = detect_widget_type(properties)

    # Check if this is a screen (top-level object)
    is_screen = screen_name is None

    if is_screen:
        # This is a screen - use the object name as screen name
        screen_name = obj_name
        stats['screens'] += 1
        new_codename = f"Screen_{obj_name}"

        # Update the codename
        codename_prop = get_codename_property(properties)
        if codename_prop:
            old_codename = codename_prop.get('strval', '')
            if old_codename != new_codename:
                print(f"  Screen: {old_codename} -> {new_codename}")
                codename_prop['strval'] = new_codename
                stats['renamed'] += 1
    else:
        # This is a child widget
        # Build new codename: ScreenName_WidgetType_ObjectName
        new_codename = f"{screen_name}_{widget_type}_{obj_name}"

        # Update the codename
        codename_prop = get_codename_property(properties)
        if codename_prop:
            old_codename = codename_prop.get('strval', '')
            if old_codename != new_codename:
                print(f"    [{widget_type:12}] {old_codename} -> {new_codename}")
                codename_prop['strval'] = new_codename
                stats['renamed'] += 1

    # Process children
    for child in obj.get('children', []):
        process_object(child, screen_name, stats)

    return obj_name

def process_project(data):
    """Process the entire project."""
    stats = {'renamed': 0, 'screens': 0, 'total': 0}

    root = data.get('root', {})

    # Process all top-level children (screens)
    for child in root.get('children', []):
        process_object(child, None, stats)

    return stats

def main():
    if len(sys.argv) < 2:
        print("Usage: python rename_components.py <input.spj> [output.spj]")
        print("\nThis script renames all SquareLine components to follow the convention:")
        print("  [ScreenName]_[WidgetType]_[ObjectName]")
        sys.exit(1)

    input_file = Path(sys.argv[1])

    if len(sys.argv) >= 3:
        output_file = Path(sys.argv[2])
    else:
        # Create backup and modify in place
        backup_file = input_file.with_suffix(f'.backup_{datetime.now().strftime("%Y%m%d_%H%M%S")}.spj')
        shutil.copy(input_file, backup_file)
        print(f"Created backup: {backup_file}")
        output_file = input_file

    print(f"Reading: {input_file}")

    with open(input_file, 'r', encoding='utf-8') as f:
        data = json.load(f)

    print("\nProcessing components...")
    stats = process_project(data)

    print(f"\nWriting: {output_file}")
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2)

    print(f"\n{'='*50}")
    print(f"Summary")
    print(f"{'='*50}")
    print(f"Total objects:  {stats['total']}")
    print(f"Screens found:  {stats['screens']}")
    print(f"Items renamed:  {stats['renamed']}")
    print(f"\nDone! Open the project in SquareLine Studio to verify changes.")
    print(f"Then re-export your UI code.")

if __name__ == '__main__':
    main()
