/*******************************************************************************
 * Size: 16 px
 * Bpp: 1
 * Opts: --bpp 1 --size 16 --font /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/SquareLineConsole/assets/MaterialSymbolsOutlined-Regular.ttf -o /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/SquareLineConsole/assets/ui_font_Icon2.c --format lvgl -r 0xE63E -r 0xEF02 -r 0xE5D5 -r 0xE8B8 -r 0xE002 -r 0xE8BE -r 0xE1A7 -r 0xE8FD -r 0xE88E -r 0xE192 -r 0xE2A7 -r 0xEB2F -r 0xE863 --no-compress --no-prefilter
 ******************************************************************************/

#include "ui.h"

#ifndef UI_FONT_ICON2
#define UI_FONT_ICON2 1
#endif

#if UI_FONT_ICON2

/*-----------------
 *    BITMAPS
 *----------------*/

/*Store the image of the glyphs*/
static LV_ATTRIBUTE_LARGE_CONST const uint8_t glyph_bitmap[] = {
    /* U+E002 "" */
    0x0, 0x0, 0xc, 0x0, 0x30, 0x1, 0xe0, 0x4,
    0x80, 0x33, 0x1, 0xb6, 0x4, 0xc8, 0x33, 0x30,
    0x80, 0x46, 0x31, 0x90, 0x2, 0xff, 0xfc,

    /* U+E192 "" */
    0xf, 0x81, 0x83, 0x18, 0xc, 0x84, 0x28, 0x20,
    0xc1, 0x6, 0xc, 0x30, 0x71, 0x80, 0xca, 0x0,
    0x98, 0xc, 0x60, 0xc0, 0xf8, 0x0,

    /* U+E1A7 "" */
    0x8, 0xc, 0xce, 0x6b, 0x3e, 0x1c, 0x8, 0x1c,
    0x3e, 0x6b, 0xce, 0xc, 0x8,

    /* U+E2A7 "" */
    0x3, 0xc0, 0x17, 0x81, 0xf2, 0x0, 0x10, 0x0,
    0x80, 0x5, 0xfe, 0x28, 0x31, 0x41, 0x8f, 0xe,
    0x6f, 0xd3, 0x0, 0x9f, 0xfc, 0xff, 0xe4,

    /* U+E5D5 "" */
    0x1e, 0x58, 0x74, 0xe, 0xf, 0x80, 0x20, 0x8,
    0x5, 0x2, 0x61, 0x87, 0x80,

    /* U+E63E "" */
    0xf, 0xe0, 0x7f, 0xf1, 0xe0, 0xf7, 0x0, 0x70,
    0x7c, 0x3, 0xfe, 0x6, 0xc, 0x0, 0x0, 0x3,
    0x80, 0x7, 0x0, 0xe, 0x0,

    /* U+E863 "" */
    0xc, 0x1, 0x81, 0xf1, 0x98, 0x4c, 0x60, 0x18,
    0x6, 0x1, 0x80, 0x63, 0x21, 0x98, 0xf8, 0x18,
    0x3, 0x0,

    /* U+E88E "" */
    0xf, 0x81, 0x83, 0x18, 0xc, 0x84, 0x28, 0x20,
    0xc0, 0x6, 0x8, 0x30, 0x41, 0x82, 0xa, 0x10,
    0x98, 0xc, 0x60, 0xc0, 0xf8, 0x0,

    /* U+E8B8 "" */
    0x7, 0x80, 0x12, 0x7, 0xcf, 0x94, 0xa, 0xc7,
    0x8d, 0x9e, 0x62, 0xfd, 0x19, 0xe6, 0xc7, 0x8d,
    0x40, 0xa7, 0xcf, 0x81, 0x20, 0x7, 0x80,

    /* U+E8BE "" */
    0x18, 0x30, 0x60, 0x31, 0x80, 0x36, 0x49, 0x36,
    0x0, 0xc6, 0x3, 0x6, 0xc, 0x0,

    /* U+E8FD "" */
    0xf, 0x81, 0x83, 0x18, 0xcc, 0x9f, 0x28, 0x8,
    0xc0, 0xc6, 0xc, 0x30, 0x41, 0x80, 0xa, 0x10,
    0x98, 0x8c, 0x60, 0xc0, 0xf8, 0x0,

    /* U+EB2F "" */
    0x1f, 0x81, 0x98, 0x19, 0x81, 0x98, 0x1f, 0x80,
    0x60, 0x3f, 0xc2, 0x4, 0x20, 0x4f, 0x9f, 0x89,
    0x18, 0x91, 0xf9, 0xf0,

    /* U+EF02 "" */
    0x0, 0x6, 0x0, 0xc, 0x1, 0x98, 0x3, 0x30,
    0x36, 0x60, 0x6c, 0xc6, 0xd9, 0xed, 0xb3, 0xdb,
    0x67, 0xb6, 0xcf, 0x6d, 0x98
};


/*---------------------
 *  GLYPH DESCRIPTION
 *--------------------*/

static const lv_font_fmt_txt_glyph_dsc_t glyph_dsc[] = {
    {.bitmap_index = 0, .adv_w = 0, .box_w = 0, .box_h = 0, .ofs_x = 0, .ofs_y = 0} /* id = 0 reserved */,
    {.bitmap_index = 0, .adv_w = 256, .box_w = 14, .box_h = 13, .ofs_x = 1, .ofs_y = 2},
    {.bitmap_index = 23, .adv_w = 256, .box_w = 13, .box_h = 13, .ofs_x = 2, .ofs_y = 2},
    {.bitmap_index = 45, .adv_w = 256, .box_w = 8, .box_h = 13, .ofs_x = 4, .ofs_y = 1},
    {.bitmap_index = 58, .adv_w = 256, .box_w = 13, .box_h = 14, .ofs_x = 2, .ofs_y = 1},
    {.bitmap_index = 81, .adv_w = 256, .box_w = 10, .box_h = 10, .ofs_x = 3, .ofs_y = 3},
    {.bitmap_index = 94, .adv_w = 256, .box_w = 15, .box_h = 11, .ofs_x = 0, .ofs_y = 2},
    {.bitmap_index = 115, .adv_w = 256, .box_w = 10, .box_h = 14, .ofs_x = 3, .ofs_y = 1},
    {.bitmap_index = 133, .adv_w = 256, .box_w = 13, .box_h = 13, .ofs_x = 2, .ofs_y = 2},
    {.bitmap_index = 155, .adv_w = 256, .box_w = 14, .box_h = 13, .ofs_x = 1, .ofs_y = 2},
    {.bitmap_index = 178, .adv_w = 256, .box_w = 15, .box_h = 7, .ofs_x = 1, .ofs_y = 5},
    {.bitmap_index = 192, .adv_w = 256, .box_w = 13, .box_h = 13, .ofs_x = 2, .ofs_y = 2},
    {.bitmap_index = 214, .adv_w = 256, .box_w = 12, .box_h = 13, .ofs_x = 2, .ofs_y = 2},
    {.bitmap_index = 234, .adv_w = 256, .box_w = 15, .box_h = 11, .ofs_x = 1, .ofs_y = 3}
};

/*---------------------
 *  CHARACTER MAPPING
 *--------------------*/

static const uint16_t unicode_list_0[] = {
    0x0, 0x190, 0x1a5, 0x2a5, 0x5d3, 0x63c, 0x861, 0x88c,
    0x8b6, 0x8bc, 0x8fb, 0xb2d, 0xf00
};

/*Collect the unicode lists and glyph_id offsets*/
static const lv_font_fmt_txt_cmap_t cmaps[] =
{
    {
        .range_start = 57346, .range_length = 3841, .glyph_id_start = 1,
        .unicode_list = unicode_list_0, .glyph_id_ofs_list = NULL, .list_length = 13, .type = LV_FONT_FMT_TXT_CMAP_SPARSE_TINY
    }
};



/*--------------------
 *  ALL CUSTOM DATA
 *--------------------*/

#if LVGL_VERSION_MAJOR == 8
/*Store all the custom data of the font*/
static  lv_font_fmt_txt_glyph_cache_t cache;
#endif

#if LVGL_VERSION_MAJOR >= 8
static const lv_font_fmt_txt_dsc_t font_dsc = {
#else
static lv_font_fmt_txt_dsc_t font_dsc = {
#endif
    .glyph_bitmap = glyph_bitmap,
    .glyph_dsc = glyph_dsc,
    .cmaps = cmaps,
    .kern_dsc = NULL,
    .kern_scale = 0,
    .cmap_num = 1,
    .bpp = 1,
    .kern_classes = 0,
    .bitmap_format = 0,
#if LVGL_VERSION_MAJOR == 8
    .cache = &cache
#endif
};



/*-----------------
 *  PUBLIC FONT
 *----------------*/

/*Initialize a public general font descriptor*/
#if LVGL_VERSION_MAJOR >= 8
const lv_font_t ui_font_Icon2 = {
#else
lv_font_t ui_font_Icon2 = {
#endif
    .get_glyph_dsc = lv_font_get_glyph_dsc_fmt_txt,    /*Function pointer to get glyph's data*/
    .get_glyph_bitmap = lv_font_get_bitmap_fmt_txt,    /*Function pointer to get glyph's bitmap*/
    .line_height = 14,          /*The maximum line height required by the font*/
    .base_line = -1,             /*Baseline measured from the bottom of the line*/
#if !(LVGL_VERSION_MAJOR == 6 && LVGL_VERSION_MINOR == 0)
    .subpx = LV_FONT_SUBPX_NONE,
#endif
#if LV_VERSION_CHECK(7, 4, 0) || LVGL_VERSION_MAJOR >= 8
    .underline_position = 0,
    .underline_thickness = 0,
#endif
    .dsc = &font_dsc,          /*The custom font data. Will be accessed by `get_glyph_bitmap/dsc` */
#if LV_VERSION_CHECK(8, 2, 0) || LVGL_VERSION_MAJOR >= 9
    .fallback = NULL,
#endif
    .user_data = NULL,
};



#endif /*#if UI_FONT_ICON2*/

