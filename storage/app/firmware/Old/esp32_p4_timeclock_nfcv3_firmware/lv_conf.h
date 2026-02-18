#ifndef LV_CONF_H
#define LV_CONF_H

/* Let LVGL locate this file simply via #include "lv_conf.h" */
#define LV_CONF_INCLUDE_SIMPLE 1

/* Turn off backends not used on ESP32 */
#define LV_USE_GPU_ARM2D      0     /* <- this removes lv_gpu_arm2d.c from build */
#define LV_USE_DRAW_ARM2D     0

/* Usual core enables (safe defaults) */
#define LV_USE_LOG            0
#define LV_COLOR_DEPTH        16
#define LV_MEM_CUSTOM         0

/* Tick: you can keep default; if you have your own, set LV_TICK_CUSTOM 1 and provide it */
#define LV_TICK_CUSTOM        0

#endif /* LV_CONF_H */