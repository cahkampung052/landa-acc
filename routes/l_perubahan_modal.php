<?php

$app->get('/acc/l_perubahan_modal/laporan', function ($request, $response) {
    $subDomain = str_replace('api/', '', site_url());
    $data['img'] = imgLaporan();
    $params = $request->getParams();
    $sql = $this->db;
    $arr = [];
    /**
     * tanggal awal
     */
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * tanggal akhir
     */
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * Format Tanggal
     */
    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");
    /**
     * Siapkan sub header laporan
     */
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = (isset($params['nama_lokasi']) && !empty($params['nama_lokasi'])) ? $params['nama_lokasi'] : "Semua";
    /*
     * Siapkan parameter lokasi
     */
    if (isset($params['m_lokasi_id'])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if (!empty($lokasiId)) {
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */ else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    /**
     * Proses laporan
     */
    if (isset($params['m_lokasi_id'])) {

        /*
         * Ambil akun modal
         */
        $modal = getPemetaanAkun("Modal");
        $akun_modal = isset($modal[0]) ? $modal[0] : 0;

        /**
         * Ambil saldo awal akun modal
         */
        $saldo_awal_modal = $saldo_periode_modal = $saldo_akhir_modal = 0;
        $sql->select("
                  id,
                  SUM(debit) as debit,
                  SUM(kredit) as kredit
                ")
                ->from("acc_trans_detail");

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
        }

        $sql->where('m_akun_id', '=', $akun_modal)
                ->andWhere('date(tanggal)', '<', $tanggal_start);
        $getSaldoAwalModal = $sql->find();

        $arr['saldo_awal_modal'] = $getSaldoAwalModal->debit - $getSaldoAwalModal->kredit;

//        print_die($getSaldoAwalModal);

        /**
         * Ambil saldo periode akun modal
         */
        $sql->select("
                  id,
                  SUM(debit) as debit,
                  SUM(kredit) as kredit
                ")
                ->from("acc_trans_detail");

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
        }

        $sql->where('m_akun_id', '=', $akun_modal)
                ->andWhere('date(tanggal)', '>=', $tanggal_start)
                ->andWhere('date(tanggal)', '<=', $tanggal_end);
        $getSaldoPeriodeModal = $sql->find();

        $arr['saldo_periode_modal'] = $getSaldoPeriodeModal->debit - $getSaldoPeriodeModal->kredit;

        /*
         * Ambil akun prive
         */
        $prive = getPemetaanAkun("Prive");
        $akun_prive = isset($prive[0]) ? $prive[0] : 0;

        /*
         * ambil saldo periode akun prive
         */
        $sql->select("
                  id,
                  SUM(debit) as debit,
                  SUM(kredit) as kredit
                ")
                ->from("acc_trans_detail");

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
        }

        $sql->where('m_akun_id', '=', $akun_prive)
                ->andWhere('date(tanggal)', '>=', $tanggal_start)
                ->andWhere('date(tanggal)', '<=', $tanggal_end);
        $getSaldoPeriodePrive = $sql->find();

        $arr['saldo_periode_prive'] = $getSaldoPeriodePrive->debit - $getSaldoPeriodePrive->kredit;

        /**
         * Ambil akun laba rugi
         */
        $labarugi = getPemetaanAkun("Laba Rugi Berjalan");
        $akunLabaRugi = isset($labarugi[0]) ? $labarugi[0] : 0;
        $saldoLabaRugi = getLabaRugiNominal($tanggal_start, $tanggal_end, null);
        $totalLabaRugi = $saldoLabaRugi["total"];

        $arr['saldo_laba_rugi'] = $totalLabaRugi;

        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/bukuBesar.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/bukuBesar.html', [
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
        return unprocessResponse($response, ["Silahkan pilih lokasi dan akun terlebih dahulu"]);
    }
});
