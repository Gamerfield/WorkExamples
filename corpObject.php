<?php

	class Corp {
		public function GetCorp ($NewID) {
			// Gathers all of the core information about the corp for Read
			$CorpRow = SQLGetRow("SELECT * FROM Corp WHERE ID = {$NewID}");
			if(vd($CorpRow['ID']) == '') {
				WTF("GetCorp ID failed", true);
			} else {
				// Setting each Corp column as it's own value
				foreach($CorpRow AS $Column => $Value) {
					$this->$Column = $Value;
				}
				
				// Getting Lots
				$this->GetLots();
				
				// Getting Corp Attributes
				$this->GetCorpAttributes();
				
				// Setting BrandID
				$BrandData = SQLGetRow("SELECT * FROM Corp WHERE ID = {$this->parent_id}");
				while($BrandData['brand'] != 1) {
					$BrandData = SQLGetRow("SELECT * FROM Corp WHERE ID = {$BrandData['parent_id']}");
				}
				$this->BrandID = $BrandData['ID'];
				
			}
			return $this;
		}
		
		public function UpdateCorpTable ($data) {
			// Expects key value pairs with key = Corp.ColumnName, Value = new value
			$results = array();
			foreach($data AS $column => $newValue) {
				if(isset($this->$column) && $this->$column != $newValue) {
					if($column != 'shortcodebase') {
						$newValue = SQLSafe($newValue);
						$column = str_replace('"', '', SQLSafe($column));
						SQLGo("UPDATE Corp SET {$column} = {$newValue} WHERE ID = {$this->ID}");
						$results[$column] = 'Success';
					} else {
						$results[$column] = $this->UpdateShortCodeBase($newValue);
					}
				} else if($this->$column == $newValue) {
					$results[$column] = 'Not updated, value did not change.';
				} else {
					$results[$column] = 'Not updated, column does not exist.';
				}
			}
			return $results;
		}
		
		public function GetLots() {
			// Set Corp Lots
			// Also Creates an array of LotIDsByName
			$CorpLot = SQL2Arrays("SELECT * FROM CorpLot WHERE CorpID = {$this->ID}");
			$Lots = array();
			$LotNames = array();
			$LotCodes = array();
			foreach($CorpLot AS $CLRow) {
				$LotNames[$CLRow['Name']] = $CLRow['ID'];
				$LotCodes[$CLRow['Name']] = $CLRow['code'];
				$Lots[$CLRow['ID']] = $CLRow;
			}
			$this->Lots = $Lots;
			$this->LotIDsByName = $LotNames;
			$this->LotCodesByName = $LotCodes;
		}
		
		public function NewLot($data) {
			$SQLLotName = SQLSafe($data['NewLotName'],1);
			$SQLExternal = SQLSafe($data['NewLotExternal'], 1);
			$SQLLotCode = SQLSafe($data['NewLotCode']);
			$SQLLotID = SQLSafe(vd($data['NewLotID'], 0));
			
			if(!is_numeric($SQLExternal)) {
				return 'External parameter is not numeric.';
			}
			
			$GoodName = true;
			
			// Check Lot Exists
			$this->GetLots();
			foreach($this->LotIDsByName AS $Name => $ID) {
				if(strtoupper($Name) == strtoupper($SQLLotName)) {
					$GoodName = false;
				}
			}
			
			if($SQLLotName != '' && $GoodName) {
				SQLGo("INSERT INTO CorpLot (CorpID, Name, Code, External, LotID) VALUES ({$this->ID}, '{$SQLLotName}', {$SQLLotCode}, {$SQLExternal}, {$SQLLotID})");
				$this->GetLots();
				return 'Success';
			} else {
				return 'Bad Lot Name '.$SQLLotName.'.';
			}
		}
		
		public function DeleteLotByLotID($data) {
			// Deletes based on CorpLot.LotID (NOT CorpLot.ID)
			$SQLLotID = SQLSafe($data['DeleteLotID'], 1);
			
			if($SQLLotID > 0) {
				$CheckEmpty = SQLGetRow("SELECT ID FROM UserLot WHERE CorpID = {$this->ID} AND LotID={$SQLLotID} LIMIT 1");
				if(vd($CheckEmpty['ID']) == '') {
					SQLGo("DELETE FROM CorpLot WHERE CorpID = {$this->ID} AND LotID = {$SQLLotID}");
					return 'Success';
				} else {
					return 'Cannot delete lot, lot is not empty.';
				}
			} else {
				return 'Bad Lot ID '.$SQLLotID.'.';
			}
		}
		
		public function GetCorpAttributes () {
			unset($this->Attributes);
			$CorpAttributes = SQL2Arrays("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} ORDER BY Attribute ASC");
			$Attributes = array();
			foreach($CorpAttributes AS $CARow) {
				$Attributes[$CARow['Attribute']] = $CARow['Value'];
			}
			$this->Attributes = $Attributes;
			if(vd($this->Attributes['PackageLevel']) == '') {
				$this->Attributes['PackageLevel'] = 'Custom Package';
			}
		}
		
		public function AddNewCorpAttribute ($data) {
			// requires the following:
			$SQLAttribute = SQLSafe($data['Attribute']);
			$SQLValue = SQLSafe($data['Value']);
			
			$CheckAttributeExists = SQLGetRow("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = {$SQLAttribute}");
			if(vd($CheckAttributeExists['ID']) == '') {
				SQLGo("INSERT INTO CorpAttribute (CorpID, Attribute, Value) VALUES ({$this->ID}, {$SQLAttribute}, {$SQLValue})");
				$this->GetCorpAttributes();
				return 'Success';
			} else {
				// not updating in case attribute is Read Only
				return 'Corp Attribute already exists.';
			}
		}
		
		public function UpdateCorpAttribute ($data) {
			// requires the following:
			$SQLAttribute = SQLSafe($data['Attribute']);
			$SQLValue = SQLSafe($data['Value']);
			
			$CheckAttributeExists = SQLGetRow("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = {$SQLAttribute}");
			if(vd($CheckAttributeExists['ID']) != '') {
				SQLGo("UPDATE CorpAttribute SET Value = {$SQLValue} WHERE CorpID = {$this->ID} AND Attribute = {$SQLAttribute}");
				$this->GetCorpAttributes();
				return 'Success';
			} else {
				// not inserting
				return 'Corp Attribute does not exist.';
			}
		}
		
		public function DeleteCorpAttribute ($attribute) {
			$SQLAttribute = SQLSafe($attribute);
			
			$CheckAttributeExists = SQLGetRow("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = {$SQLAttribute}");
			if(vd($CheckAttributeExists['ID']) != '') {
				SQLGo("DELETE FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = {$SQLAttribute}");
				$this->GetCorpAttributes();
				return 'Success';
			} else {
				// not inserting
				return 'Corp Attribute does not exist.';
			}
		}
		
		public function UpdateShortCodeBase($newCode) {
			// expects new shortcodebase
			if($this->shortcodebase == $newCode) {
				return 'Not updated, code did not change.';
			} else {
				$oldCode = $this->shortcodebase;
				SQLGo("UPDATE Corp SET shortcodebase = ".SQLSafe($newCode)." WHERE ID = {$this->ID}");
				foreach($this->Lots AS $LotID => $Row) {
					$fullNewCode = '';
					// verify Lot belongs to Corp
					$CheckRow = SQLGetRow("SELECT * FROM CorpLot WHERE CorpID = {$this->ID} AND ID = {$LotID}"); 
					if(vd($CheckRow['ID']) == $LotID) {
						if($CheckRow['code'] != null) { // skip nulls (Archive)
							$editingCode = explode("-", $CheckRow['code']);
							$lotExtension = vd($editingCode[1], '');
							if($lotExtension != '') {
								$fullNewCode = $newCode.'-'.$lotExtension;
							} else {
								$fullNewCode = $newCode;
							}
							$fullNewCode = SQLSafe($fullNewCode);
							SQLGo("UPDATE CorpLot SET code = {$fullNewCode} WHERE ID = {$LotID}");
						}
					}
				}
			}
			$this->GetLots();
			return $this->Lots;
		}
		
		public function GetCorpUsers () {
			// Grabs all users in an organization. 
			// Adds each user to Obj as: AllUsers
			// Creates additional arrays for each Lot as: Lot$ID 
			unset($this->AllUsers);
			// unset($this->Lots);
			$UserLot = SQL2Arrays("SELECT * FROM UserLot WHERE CorpID = {$this->ID}");
			$User = SQL2Arrays("SELECT U.ID, U.FirstName, U.LastName FROM User AS U LEFT JOIN UserLot AS UL ON UL.UserID = U.ID WHERE UL.CorpID = {$this->ID}");
			$Users_t = array();
			foreach($UserLot AS $Row) {
				if(isset($User[$Row['UserID']]['FirstName'])) {
					$Users_t[$Row['LotID']][$Row['UserID']] = array('UserID' => $Row['UserID'], 'FirstName' => $User[$Row['UserID']]['FirstName'], 'LastName' => $User[$Row['UserID']]['LastName']);
					$this->AllUsers[$Row['UserID']] = array('UserID' => $Row['UserID'], 'FirstName' => $User[$Row['UserID']]['FirstName'], 'LastName' => $User[$Row['UserID']]['LastName']);
				}
			}
			foreach($this->Lots AS $LotID => $Lot) {
				$ObjName = 'Lot'.$LotID;
				$this->$ObjName = vd($Users_t[$LotID], array());
			}
			
			return $this;
		}
		
		public function GetCorpPaymentStatus () {
			unset($this->PaymentHistory);
			unset($this->PaymentStatus);
			$CorpPaymentStatus = SQL2Arrays("SELECT CPS.*, date_format(CPS.notedts, '%Y-%m-%d') AS NoteDate, U.FirstName AS Name FROM CorpPaymentStatus AS CPS LEFT JOIN User AS U ON CPS.updatedbyUserID = U.ID WHERE CorpID = {$this->ID} ORDER BY ID DESC");
			$this->PaymentHistory = $CorpPaymentStatus;
			$this->PaymentStatus = current($CorpPaymentStatus);
			return $this;
		}
		
		public function NewCorpPaymentStatus ($data) {
			// requires the following:
			$status = $data['Status'];
			$enddate = $data['EndDate'];
			$note = $data['Note'];
			$notedts = $data['NoteDate'];
			
			$UserID = vd($_SESSION['ID'], -1);
			
			$SQLStatus = SQLSafe($status);
			$SQLenddate = SQLSafe($enddate);
			if($SQLenddate == '""') {
				$SQLenddate = 'NULL';
			}
			
			$SQLNote = SQLSafe($note);
			$SQLnotedts = SQLSafe($notedts);
			
			// Update CorpStatus in Corp Row
			// -1 = archive
			//  0 = unknown
			//  1 = Freemium
			//  2 = scholarship
			//  3 = paying
			//  4 = trial
			switch ($SQLStatus) {
				case '"Free - Scholarship"':
					$CorpStatus = 2;
					break;
				case '"Free - Trial"':
					$CorpStatus = 4;
					break;
				case '"Free - Expired"':
					$CorpStatus = 1;
					break;
				case '"Free - Freemium"':
					$CorpStatus = 1;
					break;
				case '"Paying - Invoice"':
					$CorpStatus = 3;
					break;
				case '"Paying - Stripe Subscription"':
					$CorpStatus = 3;
					break;
				case '"Paying - Partner"':
					$CorpStatus = 3;
					break;
				case '"Archive"':
					$CorpStatus = -1;
					break;
				default:
					$CorpStatus = 0;
					break;
				
			}
			SQLGo("UPDATE Corp SET CorpStatus = {$CorpStatus} WHERE ID = {$this->ID}");
			
			SQLGo("INSERT INTO CorpPaymentStatus (CorpID, updatedbyUserID, status, enddate, note, notedts) VALUES ({$this->ID}, {$UserID}, {$SQLStatus}, {$SQLenddate}, {$SQLNote}, {$SQLnotedts})");
			$this->GetCorpPaymentStatus();
			return 'Success';
		}
		
		public function GetCorpReports () {
			unset($this->PackageReports);
			unset($this->NonPackageReports);
			unset($this->MissingPackageReports);
			$PLevel = vd($this->Attributes['PackageLevel'], 'Custom Package');
			if($PLevel == 'Custom Package') {
				$PackageID['ID'] = -1;
				$PackageID = $PackageID['ID'];
				$PackageReports = array();
			} else {
				$PackageID = SQLGetRow("SELECT * FROM Packages WHERE Name LIKE '%{$PLevel}%' AND TeamPackage = 1 AND Active = 1 AND BrandCorpID LIKE '%{$this->BrandID}%' LIMIT 1");
				$PackageID = vd($PackageID['ID'], -1);
				$PackageReports = SQL2Array("SELECT ReportID AS ID, ReportID AS Title FROM PackagesReports WHERE PackagesID = {$PackageID}");
			}
			$CorpReports = SQL2Arrays("SELECT * FROM CorpReport WHERE CorpID = {$this->ID}");
			$AllReports = SQL2Arrays("SELECT * FROM Report WHERE 1");
			foreach($CorpReports AS $Rep) {
				$Rep['Title'] = $AllReports[$Rep['ReportID']]['Title'];
				$Rep['ReportTypeID'] = $AllReports[$Rep['ReportID']]['ReportTypeID'];
				$this->AllReports[$Rep['ReportID']] = $Rep;
				if(in_array($Rep['ReportID'], $PackageReports)) {
					$this->PackageReports[$Rep['ReportID']] = $Rep;
				} else {
					$this->NonPackageReports[$Rep['ReportID']] = $Rep;
				}
			}
			if(!empty($CorpReports)) {
				$this->MissingPackageReports = SQL2Arrays("SELECT PR.ReportID AS ID, PR.*, R.Title, R.ReportTypeID FROM PackagesReports AS PR LEFT JOIN Report AS R ON R.ID = PR.ReportID WHERE PR.PackagesID = {$PackageID} AND PR.ReportID NOT IN (".implode(",",array_column($CorpReports, 'ReportID')).")");
			}
			return $this;
		}
		
		public function AddNonPackageReport ($data) {
			// requires the following:
			$CorpFree = $data['CorpFree'];
			$CorpOnly = $data['CorpOnly'];
			$ReportID = $data['ReportID'];
			
			$SQLReportID = SQLSafe($data['ReportID']);
			$SQLCorpOnly = SQLSafe($CorpOnly);
			$SQLFree = SQLSafe($CorpFree);
			
			$CheckReportExists = SQLGetRow("SELECT * FROM CorpReport WHERE CorpID = {$this->ID} AND ReportID = {$SQLReportID}");
			
			if(vd($CheckReportExists['ID']) == '') {
				SQLGo("INSERT INTO CorpReport (CorpID, free, CorporateOnly, ReportID) VALUES ({$this->ID}, {$SQLFree}, {$SQLCorpOnly}, {$SQLReportID})");
				$this->GetCorpReports();
				return 'Success';
			} else {
				// don't want to update here just in case this is a package report
				return 'Report already exists.';
			}
			
		}
		
		public function FixPackageReport ($data) {
			$FixReportID = $data['ReportID'];
			$FixReportData = array();
			foreach($this->MissingPackageReports AS $Missing) {
				if(SQLSafe($Missing['ReportID']) == $FixReportID) {
					$FixReportData = $Missing;
					SQLGo("INSERT INTO CorpReport (CorpID, free, CorporateOnly, ReportID) VALUES ({$this->ID}, {$FixReportData['CorpFree']}, {$FixReportData['CorpOnly']}, {$FixReportID})");
					$this->GetCorpReports();
					return 'Success';
				}
			}
			$this->GetCorpReports();
			return 'Failed';
		}
		
		public function UpdateReportPermissions ($data) {
			// requires an array containing the following:
			// CorpFree, CorpOnly, ReportID
			
			$Free = $data['CorpFree'];
			$CorpOnly = $data['CorpOnly'];
			$ReportID = $data['ReportID'];
			
			$SQLReportID = SQLSafe($ReportID);
			$SQLCorpOnly = SQLSafe($CorpOnly);
			$SQLFree = SQLSafe($Free);
			
			$CheckReportExists = SQLGetRow("SELECT * FROM CorpReport WHERE CorpID = {$this->ID} AND ReportID = {$SQLReportID}");
			if(vd($CheckReportExists['ID']) != '') {
				SQLGo("UPDATE CorpReport SET free = {$SQLFree}, CorporateOnly = {$SQLCorpOnly} WHERE ReportID = {$ReportID} AND CorpID = {$this->ID}");
				$this->GetCorpReports();
				return 'Success';
			} else {
				return 'Report does not exist for '.$this->ID.'.';
			}
		}
		
		public function DeleteNonPackageReport ($ReportID) {
			// requires ReportID
			$this->GetCorpReports();
			
			if(array_key_exists($ReportID, $this->NonPackageReports)) {
				$SQLReportID = SQLSafe($ReportID);
				$CheckReportExists = SQLGetRow("SELECT * FROM CorpReport WHERE CorpID = {$this->ID} AND ReportID = {$SQLReportID}");
				if(vd($CheckReportExists['ID']) != '') {
					SQLGo("DELETE FROM CorpReport WHERE ReportID = {$SQLReportID} AND CorpID = {$this->ID}");
					$this->GetCorpReports();
					return 'Success';
				} else {
					return 'Report does not exist for '.$this->ID.'.';
				}
			} else {
				return 'Report is not a non-package report for '.$this->ID.'.';
			}
		}
		
		public function UpdatePackageLevel ($newPLID) {
			// Expects new package ID
			// will always delete current package
			
			// Getting old package info
			$currentPL = vd($this->Attributes['PackageLevel'], 'Custom Package');
			$PackageID = SQLGetRow("SELECT * FROM Packages WHERE Name LIKE '%{$currentPL}%' AND TeamPackage = 1 AND BrandCorpID LIKE '%{$this->BrandID}%' AND Active = 1 LIMIT 1");
			
			// Deleting current package
			if(vd($PackageID['ID'], -1) > 0) {
				$PackageReports = SQL2Array("SELECT ReportID AS ID, ReportID AS Title FROM PackagesReports WHERE PackagesID = {$PackageID['ID']}");
				foreach($PackageReports AS $RID) {
					SQLGo("DELETE FROM CorpReport WHERE CorpID = {$this->ID} AND ReportID = {$RID}");
				}
			}
			
			if($newPLID != -1) {
				$CheckCorpAttribute = SQLGetRow("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = 'PackageLevel'"); // this time for PackageLevel
				$SQLnewPL = SQLSafe($newPLID);
				$PackageName = SQLGetRow("SELECT * FROM Packages WHERE ID = {$SQLnewPL}");
				if(!isset($CheckCorpAttribute['Value'])) {
					SQLGo("INSERT INTO CorpAttribute (CorpID, Attribute, Value) VALUES ({$this->ID}, 'PackageLevel', '{$PackageName['Name']}')");
				} else {
					SQLGo("UPDATE CorpAttribute SET Value = '{$PackageName['Name']}' WHERE CorpID = {$this->ID} AND Attribute = 'PackageLevel'");
				}
				$CheckCorpAttribute = SQLGetRow("SELECT * FROM CorpAttribute WHERE CorpID = {$this->ID} AND Attribute = 'PackageID'"); // this time for PackageID
				if(!isset($CheckCorpAttribute['Value'])) {
					SQLGo("INSERT INTO CorpAttribute (CorpID, Attribute, Value) VALUES ({$this->ID}, 'PackageID', {$newPLID})");
				} else {
					SQLGo("UPDATE CorpAttribute SET Value = {$newPLID} WHERE CorpID = {$this->ID} AND Attribute = 'PackageID'");
				}
				$ReportsFrom = SQLGetRow("SELECT UseReportsFrom FROM Packages WHERE ID = {$newPLID}");
				if(vd($ReportsFrom['UseReportsFrom'],0) > 0) {
					$newPLID = $ReportsFrom['UseReportsFrom'];
				}
				$NewPackageReports = SQL2Arrays("SELECT * FROM PackagesReports WHERE PackagesID = {$newPLID}");
				foreach($NewPackageReports AS $Row) {
					$CheckReportExists = SQLGetRow("SELECT * FROM CorpReport WHERE CorpID = {$this->ID} AND ReportID = {$Row['ReportID']}");
					if(vd($CheckReportExists['ID']) == '') {
						SQLGo("INSERT INTO CorpReport(CorpID, ReportID, free, CorporateOnly) VALUES ({$this->ID}, {$Row['ReportID']}, {$Row['CorpFree']}, {$Row['CorpOnly']})");
					} else {
						SQLGo("UPDATE CorpReport SET free = {$Row['CorpFree']}, CorporateOnly = {$Row['CorpOnly']} WHERE CorpID = {$this->ID} AND ReportID = {$Row['ReportID']}");
					}
				}
			}
			$this->GetCorpAttributes();
			$this->GetCorpReports();
			return 'Success';
		}
		
	}
	
	

?>
