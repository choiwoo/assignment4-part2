<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'secret.php';
//$password is in secret.php which I have decided not to upload for privacy reasons
$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'choiwoo-db', $password, 'choiwoo-db');

if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
    exit();
}
//Table default to false as page refreshes.
$tblShown = false;

//Checking input here
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddVideo'])) {
    $input_valid = true;
    
	//video name check
    if (!isset($_POST['name']) || trim($_POST['name']) == '') {
        echo 'Enter a video name.<br>';
        $input_valid = false;
    }

	//length must be positive, and an integer.
    if (!isset($_POST['length']) || trim($_POST['length']) == '' || !isint_ref($_POST['length']) || intval($_POST['length']) < 1) {
        echo 'Enter a positive integer for video length (min).<br>';
        $input_valid = false;
    }
	//if all inputs are good
    if ($input_valid) {    
	
        $name = $_POST['name'];
        //duplicate name check
        if (mysqli_num_rows($mysqli->query("SELECT name FROM videoStore WHERE name = '$name'")))
            echo "$name you entered already exists.<br>";
        else {
		    //set variables and INSERT into table
            $cate = $_POST['category'];
            $len = $_POST['length'];
            if (!$mysqli->query("INSERT INTO videoStore (name, category, length) VALUES ('$name', '$cate', $len)"))
                echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        }
    }
}

//delete a video from table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vidDel'])) {
    $name = $_POST['vidDel'];
    $name = str_replace('_', ' ', $name);
    
    if (!$mysqli->query("DELETE FROM videoStore WHERE name = '$name'"))
        echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
}

//check in/out videos
//rented = 0 means availabe, 1 means checked out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnCheckInOutVideo'])) {
    $name = $_POST['btnCheckInOutVideo'];
    $name = str_replace('_', ' ', $name);
    // set from available to not checked out
    if (mysqli_num_rows($mysqli->query("SELECT name FROM videoStore WHERE name = '$name' AND rented = 0")))
        $mysqli->query("UPDATE videoStore SET rented = 1 WHERE name = '$name'");
    else // set from not checked out to available
        $mysqli->query("UPDATE videoStore SET rented = 0 WHERE name = '$name'");

}

//truncate all data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteAll'])) {
    if (mysqli_num_rows($mysqli->query("SELECT name FROM videoStore"))) {
        $mysqli->query("TRUNCATE TABLE videoStore"); // delete all rows
        $mysqli->query("ALTER TABLE videoStore AUTO_INCREMENT = 1"); // reset 'id' to 1
    }

}

//Filtering movies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vidFil'])) {
    $cate = $_POST['dropdown_category'];
    //call on displayTable with $cate instead of NULL
	displayTable($mysqli, $cate);
    $tblShown = true;
}
/*displayTable Function
displays table.
input: $mysqli database
input: $filterCate, if no category, NULL
output: table of the database from $mysqli
*/
function displayTable(&$mysqli, $filterCate) {
    if (!mysqli_num_rows($mysqli->query("SELECT id FROM videoStore"))) {
        return;
    }
    
    $stmt = NULL;
    if ($filterCate == NULL || $filterCate == 'all_movies')
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM videoStore ORDER BY category, name");
    else { 
	    // filter by category
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM videoStore WHERE category = '$filterCate' ORDER BY category, name");
    }
        
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $vidName = NULL;
    $vidCat = NULL;
    $vidLen = NULL;
    $vidRented = NULL;
    
    if (!$stmt->bind_result($vidName, $vidCat, $vidLen, $vidRented)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '<table border="2" <tr><td><b>Name</b></font>
            </td><td><b>Category</b></td>
            <td><b>Length</b></td><td><b>Rented</b></td>
            <td><b>Delete</b></td><td><b>Status</b></td></tr>';
    
	//fetching  data
    while ($stmt->fetch()) {
        echo "<tr><td>$vidName</td><td>$vidCat</td>
            <td>$vidLen</td>";
	    //if vidRented is true, then echo out checked out, else available
        if ($vidRented)
            echo "<td>checked out</td>";
        else
            echo "<td>available</td>";
            
        // need to prevent the string from being separated by a space in $_POST['vidName']
        $vidName = str_replace(' ', '_', $vidName);
        
		//forms for delete, and checking in/out
		echo "<td><form action='videoStore.php' method='post'>
                    <button name='vidDel' value=$vidName>Delete</button>
                </form></td>
                <td><form action='videoStore.php' method='post'>
                    <button name='btnCheckInOutVideo' value=$vidName>In/Out</button>
                </form></td>
            </tr>";
    }
    echo '</table><br>';
}

/*
displayMovieCategory function
displays dropdown menu
input: $mysqli data
output: dropdown menu for all the categories except for blank input
*/
function displayMovieCategory(&$mysqli) {
    if (!mysqli_num_rows($mysqli->query("SELECT name FROM videoStore"))) {
        //if 0 rows
		return;
    }
    //prepare and execute
    $stmt = $mysqli->prepare("SELECT category FROM videoStore GROUP BY category ORDER BY category");
    if (!$stmt->execute()) {
        echo 'Query execution failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $vidCat = NULL;
    //binding
    if (!$stmt->bind_result($vidCat)) {
        echo 'Binding failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '<select name="dropdown_category">';
	//while there are data 
    while ($stmt->fetch())
	    if(strlen($vidCat) > 0) //as long as the input wasn't empty or blank
          echo "<option value='$vidCat'>$vidCat</option>";
		
    echo "<option value='all_movies'>All Movies</option>";
    echo '</select>';
    echo "<button name='vidFil' value='filterMovies'>Filter Movies</button>";
}

//function to check for numeric
//inpute: $val
//output: true if int, false if not int.
function isint_ref(&$val) {
    $isInt = false;
    if (is_numeric($val)) {
        if (strpos($val, '.')) {
            $diff = floatval($val) - intval($val);
            if ($diff > 0)
                $isInt = false;
            else {
                $val = intval($val);
                $isInt = true;
            }
        }
        else
            $isInt = true;
    }   
    return $isInt;
}

// if table wasn't shown yet
if (!$tblShown) {
    displayTable($mysqli, NULL);
    $tblShown = true;
}
echo '<!DOCTYPE html> 
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title>VideoStore</title>
    </head>
    <body>';
    
echo "<form action='videoStore.php' method='post'>
        <fieldset>
            <legend>Add a video</legend>
            Name: <input type='text' name='name'/><br>
            Category: <input type='text' name='category'/><br>
            Length: <input type='number' name='length'/>
            <input type='submit' name='btnAddVideo' value='Add Video'/>
        </fieldset>
        <br>
        <button name='deleteAll' value='deleteAllVideo'>Delete All Videos</button>";
displayMovieCategory($mysqli);
echo '</form>
    </body>
    </html>';
mysqli_close($mysqli);
?>
