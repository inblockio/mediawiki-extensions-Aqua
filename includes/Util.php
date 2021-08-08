<?php

function getHashSum($inputStr) {
    if ($inputStr == '') {
        return '';
    }
    return hash("sha3-512", $inputStr, false);
}

function generateDomainId() {
    //*todo* import public key via wizard instead of autogenerating random
    //value
    $randomval = '';
    for( $i=0; $i<10; $i++ ) {
        $randomval .= chr(rand(65, 90));
    }
    $domain_id = getHashSum($randomval);
    //print $domain_id;
    return substr($domain_id, 0, 10);
}

function getDomainId() {
    $domain_id_filename = 'domain_id.txt';
    if (!file_exists($domain_id_filename)) {
        $domain_id = generateDomainId();
        $myfile = fopen($domain_id_filename, "w");
        fwrite($myfile, $domain_id);
        fclose($myfile);
    } else {
        //*todo* validate domain_id
        $domain_id = file_get_contents($domain_id_filename);
    }
    return $domain_id;
}

function setDAConfig($data) {
    $da_config_filename = 'data_accounting_config.json';
    if (!file_exists($da_config_filename)) {
        $da_config = $data;
    } else {
        $content = file_get_contents($da_config_filename);
        $da_config = json_decode($content, true);
        $dataArray = json_decode(json_encode($data));
        foreach ($dataArray as $key => $value) {
            $da_config[$key] = $value;
        }
    }
    file_put_contents($da_config_filename, json_encode($da_config));
}
