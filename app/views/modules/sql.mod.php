<?php
///////////////////////////////////////////////////////////////////////////////
// MealPlanner                             Penn State - Cohorts 19 & 20 @ 2018
///////////////////////////////////////////////////////////////////////////////
// SQL Module
///////////////////////////////////////////////////////////////////////////////
// SQL Server information and wrapper functions for SQL queries
///////////////////////////////////////////////////////////////////////////////
// sqlQuery()                      // INSERTs, UPDATEs, etc.
// sqlRequest()                    // SELECTs
// sqlRequestByID()                // For retrieving single values, not arrays
// sqlRequestArrayByID()           // For retrieving arrays, not single values
// sqlRequestWhere()
// sqlRequestArray()               // For retrieving multiple arrays
// sqlArrayQuery()
///////////////////////////////////////////////////////////////////////////////
// Notes:
//     Parameter format typically follows the name of the table first, then
// the ID (if applicable) then the variable(s) being searched for.
///////////////////////////////////////////////////////////////////////////////

// Constants
define( '__SQL_HN__' , 'localhost');   // MariaDB hostname
define( '__SQL_DB__' , 'capstone' );   // MariaDB database name
define( '__SQL_UN__' , 'capstone' );   // MariaDB username
define( '__SQL_PW__' , 'CmklPrew!');   // MariaDB user password

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlQuery
// ///////////////////////////////////////////////////////
// Description:
//
//     This function goes out to the SQL database and executes a query. Then
// returns the database resource by default. If a returnID is request (e.g.
// INSERT) then the new resulting id # is returned instead.
//
//////////////////////////////////////////////////////////
// Note: Used by wrapper funtions
//////////////////////////////////////////////////////////
// Returns: resource or result ID#
//////////////////////////////////////////////////////////
// See Also:
///////////////////////////////////////////////////////////////////////////////
function sqlQuery($query, $returnID = FALSE)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Start MariaDB Connection
    // //////////////////////////////////////////////////////////////////
    $dbConnection = mysqli_connect(__SQL_HN__, __SQL_UN__, __SQL_PW__) or die(mysqli_error($dbConnection));

    mysqli_select_db($dbConnection, __SQL_DB__) or die(mysqli_error($dbConnection));

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    // Execute the query statement
    // //////////////////////////////////////////////////////////////////
    //$queryResult = mysqli_query($dbConnection, $query) or die('Mysql Query['.$query.']'.mysqli_error($dbConnection)); // for debugging
    $queryResult = mysqli_query($dbConnection, $query);

    // Check if we're supposed to return an ID from an INSERT query
    if ($returnID)
    {
        // Yes we are. Retrieve the new id.
        $resultID = mysqli_insert_id($dbConnection);
    }

    // //////////////////////////////////////////////////////////////////
    // Step #3:
    // Close the Connection
    // //////////////////////////////////////////////////////////////////
    mysqli_close($dbConnection);

    // //////////////////////////////////////////////////////////////////
    // Step #4:
    // Return the requested information
    // //////////////////////////////////////////////////////////////////
    if ($returnID)
    {
        // Return the new id created from an INSERT query
        return $resultID;
    }

    return $queryResult;
}

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlRequest
// ///////////////////////////////////////////////////////
// Description:
//
//    This fucntion is used to pass SQL Queries through it that are expected
// to return data (e.g. SELECT). We run the resource that the query results in
// into sqlArrayQuery() in order to get back a relational array we can return.
//
// NOTE:
//   - Meant for being used by wrappers
//   - NOT MEANT for query strings that do not return
//     data. (i.e. INSERT, CREATE, REPLACE, etc.)
//////////////////////////////////////////////////////////
// Returns: relational array
//////////////////////////////////////////////////////////
// See Also:
//     sqlRequestByID()
//     sqlRequestArray()
//     sqlRequestWhere()
///////////////////////////////////////////////////////////////////////////////
function sqlRequest($query)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Execute the Query
    // //////////////////////////////////////////////////////////////////
    $queryResult = sqlQuery($query);

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    // Check to see if the query returned something, if so, let's
    // turn it into an array.
    // //////////////////////////////////////////////////////////////////
    if($queryResult)
        $queryResult = sqlArrayQuery($queryResult);

    // //////////////////////////////////////////////////////////////////
    // Step #3:
    // Return the Results
    // //////////////////////////////////////////////////////////////////
    return $queryResult;
}

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlRequestByID
// ///////////////////////////////////////////////////////
// Description:
//
// This function goes out to the SQL database and looks
// up the value of the variable you pass based on the id.
//
// NOTE:
//    - Returns single value, not array. if you want an
//      array by using the ID, then use :
//
//                    sqlRequestArrayByID()
//
//////////////////////////////////////////////////////////
// Returns: single value
//////////////////////////////////////////////////////////
// See Also:
//     sqlRequest()
//     sqlRequestArrayByID()
//     sqlRequestArray()
//     sqlRequestWhere()
///////////////////////////////////////////////////////////////////////////////
function sqlRequestByID($table, $id, $var)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Retrieve the data
    // //////////////////////////////////////////////////////////////////
    $returnValue = sqlRequest("select {$var} from {$table} where id = {$id}");

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    // Send back the value we were looking for. Since
    // what we receive back from sqlRequest
    // is an array, and we know we're only supposed to
    // have one answer, then we make sure that the $var
    // value sent back is from the first (#0) element
    // in the $returnValue array. Which should be the only
    // one anways.
    // //////////////////////////////////////////////////////////////////
    return $returnValue[0][$var];
}

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlRequestArrayByID
// ///////////////////////////////////////////////////////
// Description:
//
// This function goes out to the SQL database and looks up the values of the
// variables you pass, based on the id.
//
// NOTE:
//    - Returns single array not a value. If you want only a single value use:
//          sqlRequestByID()
//
//    - ONLY A SINGLE ARRAY ALWAYS, do NOT use if you can possible have the
//      query result in multiple elements in the data array being sent back.
//      Instead use: sqlRequestArray()
//
//////////////////////////////////////////////////////////
// Returns: [relational] array
//////////////////////////////////////////////////////////
// See Also:
//     sqlRequestByID()
///////////////////////////////////////////////////////////////////////////////
function sqlRequestArrayByID($table, $id, $vars)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Retrieve the data
    // //////////////////////////////////////////////////////////////////
    $returnValue = sqlRequest("select {$vars} from {$table} where id = {$id}");

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    //     Send back the value we were looking for. Since what we receive
    // receive back from sqlRequest is an array, and we know we're only
    // supposed to have one element, then we make sure that the $var
    // value sent back is from the first (#0) element in the returnValue
    // array. (Which should be the only one anyways)
    // //////////////////////////////////////////////////////////////////
    return $returnValue[0];
}

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlRequestWhere
// ///////////////////////////////////////////////////////
// Description:
//
// This function goes out to the SQL database and looks up data filtering
// through the WHERE clause and returns whatever it finds.
//
// NOTE:  Strings comparisons need to be enclosed with ticks, really meant
//        more for integer comparisons. (When looking up an ID # that is
//        not named 'id', example: roomid)
//
//////////////////////////////////////////////////////////
// Returns: relational array
//////////////////////////////////////////////////////////
// See Also:
//     sqlRequest()
//     sqlRequestArray()
//     sqlRequestByID()
///////////////////////////////////////////////////////////////////////////////
function sqlRequestWhere($table, $var, $match)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Retrieve the data
    // //////////////////////////////////////////////////////////////////
    $returnValue = sqlRequest("select * from {$table} where {$var} = {$match}");

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    // Send back the data we were looking for
    // //////////////////////////////////////////////////////////////////
    return $returnValue;
}

///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlRequestArray
// ///////////////////////////////////////////////////////
// Description:
//
// This function goes out to the SQL database and looks up the value(s) of the
// the specific variables you pass based on the id.
//
// NOTE: None
//
//////////////////////////////////////////////////////////
// Returns: relational array
//////////////////////////////////////////////////////////
// See Also:
//     sqlRequest()
//     sqlRequestByID()
//     sqlRequestWhere()
///////////////////////////////////////////////////////////////////////////////
function sqlRequestArray($table, $array)
{
    // //////////////////////////////////////////////////////////////////
    // Step #1:
    // Retrieve the data
    // //////////////////////////////////////////////////////////////////
    $returnValues = sqlRequest("select {$array} from {$table}");

    // //////////////////////////////////////////////////////////////////
    // Step #2:
    // Send back the data we were looking for
    // //////////////////////////////////////////////////////////////////
    return $returnValues;
}


///////////////////////////////////////////////////////////////////////////////
// FUNCTION: sqlArrayQuery
// ///////////////////////////////////////////////////////
// Description:
//
//     This function executes an SQL queryResource and returns a relational
// array.
//
// ///////////////////////////////////////////////////////
// Parameters:
//    $queryResource
// ///////////////////////////////////////////////////////
// Returns: relational array
///////////////////////////////////////////////////////////////////////////////
function sqlArrayQuery($queryResource)
{
    $relationalArray = array();

    // Check if we got anything to even process.
    if (!$queryResource)
    {
        return $relationalArray;
    }

    // Here we go through each item the resource returns
    while ($entry = mysqli_fetch_assoc($queryResource))
    {
        // Assign the entry we retrieve into our new array.
        $relationalArray[] = $entry;
    }

    // We've placed all the entries into the array, so now we
    // return the new relational array.
    return $relationalArray;
}

?>