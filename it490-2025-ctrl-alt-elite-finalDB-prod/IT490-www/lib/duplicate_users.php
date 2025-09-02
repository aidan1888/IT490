<?php
function users_check_duplicate($errorInfo)
{
    if ($errorInfo[1] === 1062) {
        preg_match("/Users.(\w+)/", $errorInfo[2], $matches);
        if (isset($matches[1])) {
            flash("The chosen " . $matches[1] . " is not available.", "warning");
        } else {
         
            flash("An unhandled error occured", "danger");
            error_log(var_export($errorInfo, true));
        }
    } else {
   
        flash("An unhandled error occured", "danger");
 
        error_log(var_export($errorInfo, true));
    }
}