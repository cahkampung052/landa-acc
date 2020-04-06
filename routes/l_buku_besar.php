<?php
$app->get('/acc/l_buku_besar/laporan', function ($request, $response) {
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
        /**
         * Ambil data akun
         */
        $sql->select("acc_m_akun.*, klasifikasi.nama as klasifikasi")
                ->from("acc_m_akun")
                ->leftJoin("acc_m_akun as klasifikasi", "klasifikasi.id = acc_m_akun.parent_id")
                ->andWhere("acc_m_akun.is_deleted", "=", 0)
                ->orderBy("acc_m_akun.kode ASC");
        if (isset($params['m_akun_id']) && !empty($params['m_akun_id'])) {
            $getakun = $sql->customWhere("acc_m_akun.id = '" . $params['m_akun_id'] . "'", "AND")->findAll();
        } else {
            $getakun = $sql->where("acc_m_akun.is_tipe", "=", 1)->findAll();
        }
        /**
         * Ambil akun laba rugi
         */
        $labarugi = getPemetaanAkun("Laba Rugi Berjalan");
        $akunLabaRugi = isset($labarugi[0]) ? $labarugi[0] : 0;
        $saldoLabaRugi = getLabaRugiNominal($tanggal_start, $tanggal_end, null);
        $totalLabaRugi = $saldoLabaRugi["total"];
        /**
         * Proses laporan
         */
        $index = 0;
        $arr = [];
        foreach ($getakun as $keys => $vals) {
            if ($vals->is_tipe == 1) {
                /**
                 * Ambil id akun turunan klasifikasi di parameter
                 */
                $childId = getChildId("acc_m_akun", $vals->id);
                if (!empty($childId)) {
                    $getchild = $sql->select("acc_m_akun.*, klasifikasi.nama as klasifikasi")
                            ->from("acc_m_akun")
                            ->leftJoin("acc_m_akun as klasifikasi", "klasifikasi.id = acc_m_akun.parent_id")
                            ->customWhere("acc_m_akun.id in (" . implode(",", $childId) . ")")
                            ->andWhere("acc_m_akun.is_deleted", "=", 0)
                            ->andWhere("acc_m_akun.is_tipe", "=", 0)
                            ->orderBy("acc_m_akun.kode ASC")
                            ->findAll();
                    foreach ($getchild as $key => $val) {
                        /**
                         * Ambil Saldo awal akun
                         */
                        $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                                ->from("acc_trans_detail");
                        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                            $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
                        }
                        $sql->where('m_akun_id', '=', $val->id)
                                ->andWhere('date(tanggal)', '<', $tanggal_start);
                        $getsaldoawal = $sql->find();
                        $arr[$val->id]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
                        $arr[$val->id]['debit_awal'] = intval($getsaldoawal->debit);
                        $arr[$val->id]['kredit_awal'] = intval($getsaldoawal->kredit);
                        $arr[$val->id]['akun'] = $val->kode . ' - ' . $val->nama;
                        $arr[$val->id]['akun_id'] = $val->id;
                        $arr[$val->id]['klasifikasi'] = $val->klasifikasi;
                        /**
                         * Ambil detail transaksi
                         */
                        $gettransdetail = $sql->select("*")
                                ->from("acc_trans_detail");
                        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                            $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
                        }
                        $sql->where('m_akun_id', '=', $val->id)
                                ->andWhere('date(tanggal)', '>=', $tanggal_start)
                                ->andWhere('date(tanggal)', '<=', $tanggal_end)
                                ->orderBy('tanggal');
                        $detail = $sql->findAll();
                        $saldo_sekarang = $arr[$val->id]['saldo_awal'];
                        $total_debit = $arr[$val->id]['debit_awal'];
                        $total_kredit = $arr[$val->id]['kredit_awal'];
                        /**
                         * Siapkan array laporan untuk semua akun
                         */
                        foreach ($detail as $index2 => $val2) {
                            $arr[$val->id]['detail'][$index2]['tanggal'] = $val2->tanggal;
                            $arr[$val->id]['detail'][$index2]['kode'] = $val2->kode;
                            $arr[$val->id]['detail'][$index2]['keterangan'] = $val2->keterangan;
                            $arr[$val->id]['detail'][$index2]['debit'] = $val2->debit;
                            $arr[$val->id]['detail'][$index2]['kredit'] = $val2->kredit;
                            $arr[$val->id]['detail'][$index2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
                            $saldo_sekarang += $arr[$val->id]['detail'][$index2]['saldo'];
                            $arr[$val->id]['detail'][$index2]['saldo_sekarang'] = $saldo_sekarang;
                            $total_debit += intval($val2->debit);
                            $total_kredit += intval($val2->kredit);
                        }
                        $arr[$val->id]['total_debit'] = $total_debit;
                        $arr[$val->id]['total_kredit'] = $total_kredit;
                        $arr[$val->id]['total_saldo'] = $total_debit - $total_kredit;
                        $index += 1;
                    }
                }
            } else {
                /**
                 * Ambil Saldo Awal Akun
                 */
                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail");
                if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                    $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
                }
                $sql->where('m_akun_id', '=', $vals->id)
                        ->andWhere('date(tanggal)', '<', $tanggal_start);
                $getsaldoawal = $sql->find();
                if ($vals->id == $akunLabaRugi) {
                    $arr[0]['saldo_awal'] = $totalLabaRugi;
                    $arr[0]['debit_awal'] = ($totalLabaRugi >= 0) ? $totalLabaRugi : 0;
                    $arr[0]['kredit_awal'] = ($totalLabaRugi < 0) ? $totalLabaRugi : 0;
                }else{
                    $arr[0]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
                    $arr[0]['debit_awal'] = intval($getsaldoawal->debit);
                    $arr[0]['kredit_awal'] = intval($getsaldoawal->kredit);
                }
                $arr[0]['akun'] = $vals->kode . ' - ' . $vals->nama;
                $arr[0]['klasifikasi'] = $vals->klasifikasi;
                /**
                 * Ambil detail transaksi
                 */
                $gettransdetail = $sql->select("*")
                        ->from("acc_trans_detail");
                if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                    $sql->customWhere("m_lokasi_id in (" . $lokasiId . ")");
                }
                $sql->where('m_akun_id', '=', $vals->id)
                        ->andWhere('date(tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(tanggal)', '<=', $tanggal_end)
                        ->orderBy('tanggal');
                $detail = $sql->findAll();
                /**
                 * End ambil detail transaksi
                 */
                $saldo_sekarang = 0;
                $total_debit = 0;
                $total_kredit = 0;
                foreach ($detail as $key2 => $val2) {
                    $arr[0]['detail'][$key2]['tanggal'] = $val2->tanggal;
                    $arr[0]['detail'][$key2]['kode'] = $val2->kode;
                    $arr[0]['detail'][$key2]['keterangan'] = $val2->keterangan;
                    $arr[0]['detail'][$key2]['debit'] = $val2->debit;
                    $arr[0]['detail'][$key2]['kredit'] = $val2->kredit;
                    $arr[0]['detail'][$key2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
                    $saldo_sekarang = $arr[0]['detail'][$key2]['saldo'];
                    $arr[0]['detail'][$key2]['saldo_sekarang'] = $saldo_sekarang;
                    $total_debit += intval($val2->debit);
                    $total_kredit += intval($val2->kredit);
                }
                $arr[0]['total_debit'] = $total_debit;
                $arr[0]['total_kredit'] = $total_kredit;
                $arr[0]['total_saldo'] = $arr[0]['saldo_awal'] + $total_debit - $total_kredit;
            }
        }
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
