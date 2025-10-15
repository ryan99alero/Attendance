// FILE: main/time_settings.h
#pragma once
#include "lvgl.h"

#ifdef __cplusplus
extern "C" {
#endif

void time_settings_init_nvs(void);
lv_obj_t *time_settings_create(lv_obj_t *parent);

#ifdef __cplusplus
}
#endif
