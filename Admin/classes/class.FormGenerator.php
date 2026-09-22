<?php

class FormGenerator extends dbConnect {
	private $handleID='';
	function createField($fieldName,$section,$type,$field_range,$required,$roleID=false) {
		switch ($type) {
			case "text":{
				if ($required) {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName' class='required'/>";
				}else {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName'/>";
				}
				break;
			}

			case "hidden":{
				if ($required) {
					$retval = "<input size='45' type='hidden' name='$fieldName' id='$fieldName' class='required'/>";
				}else {
					$retval = "<input size='45' type='hidden' name='$fieldName' id='$fieldName'/>";
				}
				break;
			}

			case "text_readonly": {
				if ($required) {
					$retval = "<input size='45' type='text' name='$fieldName' readonly id='$fieldName' class='required'/>";
				}else {
					$retval = "<input size='45' type='text' name='$fieldName' readonly id='$fieldName'/>";
				}
				break;
			}

			case "num":{
				if ($required) {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName' class='required' alt='num'/>";
				}else {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName' alt='num'/>";
				}
				break;
			}

			case "date":{
				$retval = "<input readonly='readonly' size='45' type='text' name='$fieldName' id='$fieldName' class='datepicker' alt='num'/>";
				break;
			}

			case "email":{
				if ($required) {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName' class='required' alt='email'/>";
				}else {
					$retval = "<input size='45' type='text' name='$fieldName' id='$fieldName' alt='email'/>";
				}
				break;
			}

			case "multi_text":{
				if ($required) {
					$retval = "<textarea cols='45' rows='3' name='$fieldName' id='$fieldName' class='required'></textarea>";
				}else {
					$retval = "<textarea cols='45' rows='3' name='$fieldName' id='$fieldName'></textarea>";
				}
				break;
			}

			case "range":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				$retval .="<option value=''></option>";
				$rows = explode(',',$field_range);
				if (sizeof($rows) > 1){
					foreach ($rows as $row) {
						$retval.='<option value="'.$row.'">'.$row.'</option>';
					}
				}else {
					$rows = explode(':',$field_range);
					if (is_array($rows)) {
						$counter = $rows[0];
						if (isset($rows[2])) {
							$step = $rows[2];
						}else {
							$step = 1;
						}
						while ($counter <= $rows['1']){
							$retval.='<option value="'.$counter.'">'.$counter.'</option>';
							$counter +=$step;
						}
					}
				}
				$retval.='</select>';
				break;

			}

			case "range_multiple":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required' multiple>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				$retval .="<option value=''></option>";
				$rows = explode(',',$field_range);
				if (sizeof($rows) > 1){
					foreach ($rows as $row) {
						$retval.='<option value="'.$row.'">'.$row.'</option>';
					}
				}else {
					$rows = explode(':',$field_range);
					if (is_array($rows)) {
						$counter = $rows[0];
						if (isset($rows[2])) {
							$step = $rows[2];
						}else {
							$step = 1;
						}
						while ($counter <= $rows['1']){
							$retval.='<option value="'.$counter.'">'.$counter.'</option>';
							$counter +=$step;
						}
					}
				}
				$retval.='</select>';
				break;

			}

			case "range_authorizer": {
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				$retval .='</select>';
				break;
			}

			

			case "yes_no":{
				$retval ="<label><input type='radio' id='$fieldName_Yes' value='Yes' name='$fieldName'/>Yes</label><br/>";
				$retval .="<label><input type='radio' id='$fieldName_No' value='No' name='$fieldName'/>No</label><br/>";
				break;
			}

			case "range_bank":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				$rows = $this->get_data("select * from Banks order by bank");
				$retval.='<option value="">Select a Bank</option>';
				foreach($rows as $row) {
					$retval.='<option value="'.$row['nsortcode'].'">'.$row['bank'].'</option>';
				}
				$retval.='</select>';
				break;
			}


			

			case "range_authoriser":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				//$retval.='<option value="Branch">Branch</option>';
				$rows = $this->get_data("select * from Admin.Users where RoleID in (49,19) and UserID != ?",[$_SESSION['uname']]);
				foreach($rows as $row) {
					$retval.='<option value="'.$row['UserID'].'">'.$row['Username'].'</option>';
				}
				$retval.='</select>';
				break;
			}

			case "range_staff":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				$rows = $this->get_data("select * from Admin.Users");
				foreach($rows as $row) {
					$retval.='<option value="'.$row['UserID'].'">'.$row['Username'].'</option>';
				}
				$retval.='</select>';
				break;
			}

			case "range_rights":{
				if ($required) {
					$retval ="<select id='$fieldName' name='$fieldName' class='required'>";
				}else {
					$retval ="<select id='$fieldName' name='$fieldName'>";
				}
				//$retval.='<option value="Branch">Branch</option>';
				$rows = $this->get_data("select * from Admin.Roles where RoleID !=1 order by Role");
				
				foreach($rows as $row) {
					$retval.='<option value="'.$row['RoleID'].'">'.$row['Role'].'</option>';
				}
				$retval.='</select>';
				break;
			}

			

			case "button":{
					$retval = "<input size='45' type='button' name='$fieldName' id='$fieldName' value='$fieldName'/>";
				break;
			}

			case "add_new_file":{
				$retval = "<input size='45' type='button' name='UploadDocument' id='UploadDocument' value='Click to Upload Documents'/><br/><br/><span id='fileUploadTable'></span>";
			break;
		}
		}
		return $retval;
	}

	function convertHTMLTblToArray($tbl)
	{
		$tbl = str_replace(' & ', ' &amp; ', $tbl);
		$DOM = new DOMDocument();
		$DOM->loadHTML($tbl);

		$Header = $DOM->getElementsByTagName('th');
		$Detail = $DOM->getElementsByTagName('td');
		foreach ($Header as $NodeHeader) {
			$aDataTableHeaderHTML[] = trim($NodeHeader->textContent);
		}
		$i = 0;
		$j = 0;
		foreach ($Detail as $sNodeDetail) {
			$aDataTableDetailHTML[$j][] = trim($sNodeDetail->textContent);
			$i = $i + 1;
			$j = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
		}

		for ($i = 0; $i < count($aDataTableDetailHTML); $i++) {
			for ($j = 0; $j < count($aDataTableHeaderHTML); $j++) {
				$aTempData[$i][$aDataTableHeaderHTML[$j]] = $aDataTableDetailHTML[$i][$j];
			}
		}
		return $aTempData;
	}


	function createExportURL($rs) {
		$filename = rand("1111111","9999999");
		$myfile = fopen("../export_files/".$filename.".txt", "w") or die("Unable to open file!");
		fwrite($myfile, serialize($rs));
		fclose($myfile);
		return $filename;
	}

	function createPDFExportURL($html) {
		$filename = rand("1111111","9999999");
		$myfile = fopen("../export_files/".$filename.".txt", "w") or die("Unable to open file!");
		fwrite($myfile, $html);
		fclose($myfile);
		return $filename;
	}

	function createTblFromArray($rs, $tbl_id) {
		// Check if the array is empty
		if (empty($rs) || !is_array($rs)) {
			return '<table id="' . $tbl_id . '" class="listing stripe" width="100%"><tbody><tr><td>No data available</td></tr></tbody></table>';
		}
		
		$tbl = '<table cellpadding="5" cellspacing="1" id="' . $tbl_id . '" class="listing stripe" width="100%">';
		
		// Get the first row for headers
		$tableHead = $rs[0];
		$tbl .= '<thead><tr>';
		
		// Create table headers - replacing the each() function
		foreach ($tableHead as $key => $value) {
			$cleanHeader = str_replace('`)', '', str_replace('sum(`', '', $key));
			$tbl .= '<th>' . $cleanHeader . '</th>';
		}
		$tbl .= '</tr></thead><tbody>';
		
		// Process table rows
		if (is_array($rs)) {
			foreach ($rs as $tblEntries) {
				$tbl .= '<tr>';
				
				// Replace each() with foreach for row values
				foreach ($tblEntries as $key => $value) {
					/*
					if (stristr($key, 'Date') !== false && $value != null) {
						
					} elseif (preg_match('/^-?[0-9,]+(\.[0-9][0-9]?)?$/', $value) || is_numeric(str_replace(',', '', $value))) {
						// Convert value to numeric by removing commas if present
						$numericValue = str_replace(',', '', $value);
						$tbl .= '<td align="right">' . number_format((float)$numericValue, 2) . '</td>';
					} else {
						$tbl .= '<td nowrap="nowrap">' . $value . '</td>';
					}
						*/
					$tbl .= '<td>' . $value . '</td>';
				}
				$tbl .= '</tr>';
			}
		}
		
		$tbl .= '</tbody></table>';
		return $tbl;
	}
 
	function createTblFromArray1($rs) {
		$filename = $this->createExportURL($rs);

		$tbl ='<table cellpadding="5" cellspacing="1"><tr><td>Export</td><td><a href="../src/exls.php?handle='.$filename.'.txt" target="_blank">XLS</a></td></tr>';
		$tbl .='<table cellpadding="5" cellspacing="1" class="listing" width="100%">';
			$tableHead = $rs[0];
			$tbl.='<tr>';
			while (list($key, $value) = each($tableHead)) {
				$tbl .='<th>'.str_replace('`)','',str_replace('sum(`','',$key)).'</th>';
			}
			$tbl.='</tr>';
			foreach($rs as $tblEntries) {
				$tbl.='<tr>';
				while (list($key, $value) = each($tblEntries)) {
					if (stristr($key, 'Date') !== false) {
						$tbl .='<td>'.date('d M, Y',strtotime($value)).'</td>';
					}else if (stristr($key, 'XML') !== false) {
						$tbl.='<td nowrap="nowrap"><textarea rows="20" cols="75">'.$value.'</textarea></td>';
					}else{
						$tbl .='<td nowrap="nowrap">'.$value.'</td>';

					}
				}
				$tbl.='</tr>';
			}
		$tbl.='</table>';
		return $tbl;
	}

	function createTblFromArray2($rs) {
		$tbl ='<table cellpadding="5" cellspacing="1" class="listing" width="100%">';
			$tableHead = $rs[0];
			$tbl.='<tr>';
			while (list($key, $value) = each($tableHead)) {
				$tbl .='<th>'.str_replace('`)','',str_replace('sum(`','',$key)).'</th>';
			}
			$tbl.='</tr>';
			foreach($rs as $tblEntries) {
				$tbl.='<tr>';
				while (list($key, $value) = each($tblEntries)) {
					/*if (stristr($key, 'Date') !== false) {
						$tbl .='<td>'.date('d M, Y',strtotime($value)).'</td>';
					}else */if (stristr($key, 'XML') !== false) {
						$tbl.='<td nowrap="nowrap"><textarea rows="20" cols="75">'.$value.'</textarea></td>';
					}else{
						$tbl .='<td nowrap="nowrap">'.$value.'</td>';

					}
				}
				$tbl.='</tr>';
			}
		$tbl.='</table>';
		return $tbl;
	}
}

?>
