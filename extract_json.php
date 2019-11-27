<?php

$api_url = 'https://api-ca.hosted.exlibrisgroup.com/almaws/v1';

$api_key = $argv[1];
$csv_file = $argv[2];

if (empty($api_key) || empty($csv_file) || !file_exists($csv_file)) {
  echo "php $argv[0] API_KEY /path/to/sfx/collection_id_link_id.csv \n";
  exit;
}

$work_dir = dirname(__FILE__) . '/output/' . $api_key;

if (!file_exists($work_dir) && !mkdir($work_dir, 0755, true)) {
  echo "Can not create output directory $work_dir";
  exit;
}

$output_csv_file = "$work_dir/matches.csv";
$output_csv = fopen($output_csv_file, 'w');

$no_matches_csv_file = "$work_dir/no_matches.csv";
$no_matches_csv = fopen($no_matches_csv_file, 'w');

$output_cmd_file = "$work_dir/update_cmd.txt";

$count = 0;
$match_count = 0;
if ( $handle = fopen($csv_file, 'r') ) {
  while (($data = fgetcsv($handle, 10000, ',')) !== false) {
    $target_service_id = $data[0];
    $general_note = $data[1];
    $target_id = $data[2];
    $target_name = $data[3];

    // skip header row
    if ($target_id == 'TARGET_ID') continue;

    $link_id_61 = '61' . $target_id;
    $link_id_62 = '62' . $target_service_id;

    $result_61 = get_e_collection_by_link_id($link_id_61, 2, 0);
    $result_62 = get_e_collection_by_link_id($link_id_62, 2, 0);

    $collection = null;
    if ($result_61->total_record_count == 1 && $result_62->total_record_count == 1) {
      // got matches with both target_id and target_service_id searches
      $c1 = $result_61->electronic_collection[0];
      $c2 = $result_62->electronic_collection[0];
      if ($c1->id == $c2->id) {
        // they are the same record, so we found a match
        $collection = get_e_collection($c1->id);
      }
    } else if ($result_61->total_record_count == 1) {
      // got exactly 1 match with target_id
      $c = $result_61->electronic_collection[0];
      $collection = get_e_collection($c->id);
    } else if ($result_62->total_record_count == 1) {
      // got exactly 1 match with target_service_id
      $c = $result_62->electronic_collection[0];
      $collection = get_e_collection($c->id);
    } else {
    }

    if ($collection) {
      $match_count++;
      $c_id = $collection->id;
      $c_public_name = $collection->public_name;

      // write original record to json file
      $json_file = "{$work_dir}/{$c_id}.json";
      write_json($json_file, json_encode($collection, JSON_PRETTY_PRINT));

      // if the note field in the CSV file matches the OUR License pattern
      // then create a link tag for the collection using that URL
      // and update the record public_note field with the new link tag
      if (preg_match('#.*(https?://[^\.]+.scholarsportal.info/licenses/.+/sfx).*#', $general_note, $matches)) {
        $src = $matches[1];
        $href = preg_replace('#/sfx$#', '', $src);
        $link = "<a href=\"$href\">License Terms of Use</a>";
        $collection->public_note = $link;

        // write updated record to json file
        $json_file = "{$work_dir}/{$c_id}_update.json";
        write_json($json_file, json_encode($collection, JSON_PRETTY_PRINT));

        // write the curl update command to file so they can be executed as a batch/shell script
        $cmd = "curl -X PUT 'https://api-ca.hosted.exlibrisgroup.com/almaws/v1/electronic/e-collections/{$c_id}?apikey={$api_key}' -H 'accept: application/json' -H 'Content-Type: application/json' -d@{$json_file} > {$json_file}.out";
        file_put_contents($output_cmd_file, $cmd . PHP_EOL , FILE_APPEND | LOCK_EX);

        fputcsv($output_csv, array($target_id,$general_note,$target_service_id,$target_name,$c_public_name,$c_id));
      } else {
        fputcsv($no_matches_csv, array($target_id,$general_note,$target_service_id,$target_name));
      }
      
    } else {
      fputcsv($no_matches_csv, array($target_id,$general_note,$target_service_id,$target_name));
    }

    $count++;
  }
}


function get_e_collection_by_link_id($link_id, $limit, $offset) {
  global $api_key;
  global $api_url;

  $url = $api_url . '/electronic/e-collections';
  $params = array(
    'apikey' => $api_key,
    'limit' => $limit,
    'offset' => $offset,
  );
  $params['q'] = "keywords~$link_id";
  $url = $url . "?" . http_build_query($params);

  return get_json($url);
}

function get_e_collection($id) {
  global $api_key;
  global $api_url;

  $url = $api_url . '/electronic/e-collections/' . $id;
  $params = array(
    'apikey' => $api_key,
  );
  $url = $url . "?" . http_build_query($params);

  return get_json($url);
}

function get_json($url) {
  $headers = array(
    'Accept: application/json'
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HEADER, FALSE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
  $response = curl_exec($ch);
  curl_close($ch);

  $o = json_decode($response);
  return $o;
}

function write_json($file, $json) {
  if ($h = fopen($file, 'w')) {
    fwrite($h, $json);
    fclose($h);
  }
}

?>
