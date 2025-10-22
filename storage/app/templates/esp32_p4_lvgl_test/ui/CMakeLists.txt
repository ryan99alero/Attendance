# SquareLine Studio UI Component

# Collect all .c files in the ui directory
file(GLOB_RECURSE UI_SOURCES ${CMAKE_CURRENT_SOURCE_DIR}/*.c)

idf_component_register(
    SRCS ${UI_SOURCES}
    INCLUDE_DIRS "."
    REQUIRES lvgl__lvgl
)
