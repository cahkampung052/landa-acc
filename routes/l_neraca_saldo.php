<?php

function validasi($data, $custom = array()) {
    $validasi = array(
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/l_neraca_saldo/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    $validasi = validasi($params);
    if ($validasi === true) {

        /*
         * tanggal awal
         */
        $tanggal_awal = new DateTime($params['startDate']);
        $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

        /*
         * tanggal akhir
         */
        $tanggal_akhir = new DateTime($params['endDate']);
        $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggal_start = $tanggal_awal->format("Y-m-d");
        $tanggal_end = $tanggal_akhir->format("Y-m-d");



        $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
        $data['disiapkan'] = date("d-m-Y, H:i");



        $arr = [];
        $data['total_debit'] = 0;
        $data['total_kredit'] = 0;

        /*
         * ambil akun
         */
        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->where("is_deleted", "=", 0)
                ->findAll();

        foreach ($getakun as $key => $val) {

            /*
             * ambil saldo awal dari akun
             */
            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            $getsaldoawal = $sql->find();
            $arr2 = [];

            $arr2['kode'] = $val->kode;
            $arr2['nama'] = $val->nama;

            $arr2['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

            /*
             * ambil transdetail dari akun where tanggal <, >
             */
            $gettransdetail = $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(tanggal)', '<=', $tanggal_end);

            $detail = $sql->find();
            $arr2['debit'] = $detail->debit;
            $arr2['kredit'] = $detail->kredit;

            $arr2['saldo_akhir'] = $arr2['saldo_awal'] + $arr2['debit'] - $arr2['kredit'];
            if ($arr2['saldo_awal'] != 0 || $arr2['debit'] != 0 || $arr2['kredit'] != 0) {
                $arr[$key] = $arr2;
                $data['total_debit'] += $arr2['debit'];
                $data['total_kredit'] += $arr2['kredit'];
            }
        }

        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/neracaSaldo.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-neraca-saldo.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/neracaSaldo.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            echo $content;
            echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
        } else {
            return successResponse($response, ["data" => $data, "detail" => $arr]);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});
