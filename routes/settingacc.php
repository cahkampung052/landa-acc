<?php
$app->get("/acc/settingacc/url", function ($request, $response) {
    $data = [
        "module_url" => config("SITE_URL") . config("MODUL_ACC")["PATH"],
    ];
    return successResponse($response, $data);
});
