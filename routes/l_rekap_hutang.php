<?php
$app->get('/acc/l_rekap_hutang/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    
//    print_r($params);die();
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
    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' s/d ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = (isset($params['nama_lokasi']) && !empty($params['nama_lokasi'])) ? $params['nama_lokasi'] : "Semua";
    $data['akun'] = $params['nama_akun'];
    /*
     * Siapkan parameter lokasi
     */
    if(isset($params['m_lokasi_id'])){
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if(!empty($lokasiId)){
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */
        else{
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    
    
    /**
     * Proses laporan
     */
    if (isset($params['m_akun_id']) && isset($params['m_lokasi_id'])) {
        
        /*
         * ambil supplier
         */
        $supplier = $sql->select("*")
                ->from("acc_m_supplier")
                ->where("is_deleted", "=", 0)
                ->findAll();
        
        $data['totalSaldoAwal'] = 0;
        $data['totalDebit'] = 0;
        $data['totalKredit'] = 0;
        $data['totalSaldoAkhir'] = 0;
        $arr=[];
        foreach($supplier as $key => $val){
            $arr[$key] = (array) $val;
            /*
            * ambil saldo awal hutang supplier
            */
           $sql->select('sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit')
               ->from('acc_trans_detail');
               if(isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])){
                   $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
               }
           $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
               ->andWhere('acc_trans_detail.m_supplier_id', '=', $val->id)
               ->andWhere('date(acc_trans_detail.tanggal)', '<', $tanggal_start);

           $getsaldoawal = $sql->find();
           $arr[$key]["saldoAwal"] = $getsaldoawal->debit - $getsaldoawal->kredit;
           
           /*
            * ambil saldo hutang di rentan tanggal dari supplier
            */
           $sql->select('sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit')
               ->from('acc_trans_detail');
               if(isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])){
                   $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
               }
           $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
                ->andWhere('acc_trans_detail.m_supplier_id', '=', $val->id)
                ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);

           $getsaldohutang = $sql->find();
           
           /*
            * isi debit kredit di detail
            */
           $arr[$key]["debit"] = ($getsaldohutang->debit != NULL) ? $getsaldohutang->debit : 0;
           $arr[$key]["kredit"] = ($getsaldohutang->kredit != NULL) ? $getsaldohutang->kredit : 0;
           
           /*
            * hitung saldo akhir detail
            */
           $arr[$key]["saldoAkhir"] = $arr[$key]["saldoAwal"] + $arr[$key]["debit"] - $arr[$key]["kredit"];
           
           /*
            * tambahkan detail ke total dari setiap saldo
            */
           $data['totalSaldoAwal'] += $arr[$key]["saldoAwal"];
           $data['totalDebit'] += $arr[$key]["debit"];
           $data['totalKredit'] += $arr[$key]["kredit"];
           $data['totalSaldoAkhir'] += $arr[$key]["saldoAkhir"];
           
        }
        
                
        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/rekapHutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-rekap-hutang.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/rekapHutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
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
