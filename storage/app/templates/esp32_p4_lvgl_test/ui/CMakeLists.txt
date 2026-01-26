SET(SOURCES ui_comp_backgroundcontainer.c
    ui_comp.c
    ui_screen_mainscreen.c
    ui_screen_adminlogin.c
    ui_screen_setupconfigurations.c
    ui_screen_wifisetup.c
    ui_screen_wiredsetup.c
    ui_screen_deviceinformation.c
    ui_screen_timeinformation.c
    ui.c
    ui_comp_hook.c
    ui_helpers.c
    ui_events.c
    ui_img_baseline_wifi_black_36dp_png.c
    fonts/ui_font_Icon2.c
    fonts/ui_font_Icons.c)

add_library(ui ${SOURCES})
