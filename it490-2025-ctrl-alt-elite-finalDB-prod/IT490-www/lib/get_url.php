<?php
function get_url($dest)
{
    global $BASE_PATH;
    if (str_starts_with($dest, "/")) {

        return $dest;
    }

    return "$BASE_PATH/$dest";
}