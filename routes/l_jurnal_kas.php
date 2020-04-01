<?php

$app->get('/acc/l_jurnal_kas/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;

    $hasil = jurnalKas($params);
    $data = $hasil['data'];
    $arr = $hasil['detail'];

//    pd($arr);

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/jurnalKas.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-jurnal-kas.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/jurnalKas.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
});
