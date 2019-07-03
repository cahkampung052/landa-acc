<?php

function validasi($data, $custom = array())
{
    $validasi = array(
//        'parent_id' => 'required',
//        'kode'      => 'required',
//        'nama'      => 'required',
        // 'tipe' => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_lokasi/getLokasi', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
                ->from("acc_m_lokasi")
                ->orderBy('acc_m_lokasi.nama')
                ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/l_budgeting/getBudgeting', function ($request, $response) {
    $params  = $request->getParams();
//    print_r($params);die();
//    echo strtotime($params['tahun']); die();
    $tahun = date('Y', strtotime($params['tahun'])) + 1;
    $db = $this->db;
    
    $db->select("acc_m_akun.*, induk.nama as nama_induk, induk.kode as kode_induk")
        ->from("acc_m_akun")
        ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
        ->orderBy('acc_m_akun.kode')
            ->where("acc_m_akun.is_deleted", "=", 0)
            ->where("acc_m_akun.is_tipe", "=", 0);
    if(isset($params['nama']) && $params['nama'] != ""){
        $db->where("acc_m_akun.nama", "LIKE", $params['nama']);
    }
      $getAkun = $db->findAll();
//    print_r($getAkun);die();
    $list=[];
    foreach ($getAkun as $key => $value) {
        $getAkun[$key] = (array) $value;
        $a=1;
        for($i=1; $i<=12; $i++){
            $getBudget = $db->select("*")->from("acc_budgeting")
                    ->where("m_akun_id", "=", $value->id)
                    ->where("tahun", "=", $tahun)
                    ->where("bulan", "=", $i)
                    ->find();
            
            $bulan = $i;
            if($i<10){
                $bulan = 0 . "" . $i;
            }
            
            $getTransDetail = $db->select("SUM(debit-kredit) AS nominal")->from("acc_trans_detail")
                    ->where("m_akun_id", "=", $value->id)
                    ->where("tanggal", "LIKE", "".$tahun."-".$bulan."")
                    ->find();
//            die();
            if(!$getBudget){
                $getAkun[$key]['detail'][$a]['nominal'] = 0;
                
            }else{
                $getAkun[$key]['detail'][$a]['nominal'] = $getBudget->budget;
                
            }
            
            if(!$getTransDetail || $getTransDetail->nominal == NULL){
                $getAkun[$key]['detail'][$a+1]['nominal'] = 0;
            }else{
                $getAkun[$key]['detail'][$a+1]['nominal'] = $getTransDetail->nominal;
            }
//            $i+=2;
            $a+=2;
        }
      
    }
//    print_r($getAkun);die();
    $listBulan = [];
    for($i=1; $i<=12; $i++){
        $listBulan[$i]['bulan'] = date('F', mktime(0, 0, 0, $i, 10));
    }
    $listSetiapBulan = [];
    $a = 1;
    for($i=1; $i<=12; $i++){
        $listSetiapBulan[$a]['nama'] = 'Target';
        $listSetiapBulan[$a+1]['nama'] = 'Realisasi';
        $a+=2;
    }
//print_r($listSetiapBulan);die();
    
    return successResponse($response, ['data' => $getAkun, 'bulan' => $listBulan, 'setiapbulan'=> $listSetiapBulan]);
});

$app->get('/acc/l_budgeting/exportExcel', function ($request, $response) {
    
    $params  = $request->getParams();
//    echo json_encode($params) ;die();
    $tahun = $params['tahun'];
//    echo $tahun;die();
    $db = $this->db;
    
    $db->select("acc_m_akun.*, induk.nama as nama_induk, induk.kode as kode_induk")
        ->from("acc_m_akun")
        ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
        ->orderBy('acc_m_akun.kode')
            ->where("acc_m_akun.is_deleted", "=", 0)
            ->where("acc_m_akun.is_tipe", "=", 0);
    if(isset($params['nama']) && $params['nama'] != ""){
        $db->where("acc_m_akun.nama", "LIKE", $params['nama']);
    }
      $getAkun = $db->findAll();
//    print_r($getAkun);die();
    foreach ($getAkun as $key => $value) {
        $getAkun[$key] = (array) $value;
        $a=1;
        for($i=1; $i<=12; $i++){
            $getBudget = $db->select("*")->from("acc_budgeting")
                    ->where("m_akun_id", "=", $value->id)
                    ->where("tahun", "=", $tahun)
                    ->where("bulan", "=", $i)->find();
            
            $bulan = $i;
            if($i<10){
                $bulan = 0 . "" . $i;
            }
            
            $getTransDetail = $db->select("SUM(debit-kredit) AS nominal")->from("acc_trans_detail")
                    ->where("m_akun_id", "=", $value->id)
                    ->where("tanggal", "LIKE", "".$tahun."-".$bulan."")
                    ->find();
//            die();
            if(!$getBudget){
                $getAkun[$key]['detail'][$a]['nominal'] = 0;
                
            }else{
                $getAkun[$key]['detail'][$a]['nominal'] = $getBudget->budget;
                
            }
            
            if(!$getTransDetail || $getTransDetail->nominal == NULL){
                $getAkun[$key]['detail'][$a+1]['nominal'] = 0;
            }else{
                $getAkun[$key]['detail'][$a+1]['nominal'] = $getTransDetail->nominal;
            }
            $a+=2;
        }
      
    }
//    echo json_encode($getAkun);die();
    $path        = 'acc/landaacc/upload/format_budgeting.xls';
    $objReader   = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);
    
    $row=6;
    foreach((array)$getAkun as $key => $val){
        $row++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['nama']);
        
        foreach(range('B','Y') as $v => $vv){
            $objPHPExcel->getActiveSheet()->setCellValue($vv . $row, $val['detail'][$v+1]['nominal']);
        }
        
//        $objPHPExcel->getRowDimension($row);
//        $objPHPExcel->setRowHeight(20);
    }
    
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_budgeting.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    
});



