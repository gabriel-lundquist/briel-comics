<?php
namespace test;

$servername = "localhost";
$username = "root";
$dbname = "briel_comics_test";
$portnum = "3307";

// This is decidedly non-general boilerplate... I'm just using it to 
// programmaticallyswitch between the similarly-formatted contwarning 
// and tag tables, which ofc I won't be able to do in every circumstance
class TableName {
    public $pageAssoc;
    public $names;
    public $idAttr;
    public function __construct($pageAssoc, $names, $idAttr) {
        $this->pageAssoc = $pageAssoc;
        $this->names = $names;
        $this->idAttr = $idAttr;
    }
    // Turns out you can't sub in table and attribute names like this
    // since PDOStatement::execute always puts quotes around strings.
    // public function getSubsArray($statementsToSub) {
    //     return [$statementsToSub[0] => $this->pageAssoc, 
    //             $statementsToSub[1] => $this->names, 
    //             $statementsToSub[2] => $this->idAttr];
    // }
}

try {
    echo "Password: ";
    $pdoConnection = new \PDO("mysql:host=$servername;dbname=$dbname;port=$portnum", 
                             $username, 
                             trim(fgets(STDIN)));
    $pdoConnection->setAttribute(\PDO::ATTR_ERRMODE, 
                                 \PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully.\n";
} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

function searchPageQuery($tableNames) {
    return 
        "SELECT DISTINCT pageid FROM " 
        . $tableNames->pageAssoc . " LEFT JOIN " . $tableNames->names 
            . " USING (" . $tableNames->idAttr 
        . ") WHERE name = :searchTerm;"
    ;
}

// $searchPageQuery = 
// "SELECT DISTINCT pageid FROM 
//   :pageAssocTable LEFT JOIN :termTable USING (:termID)
//   WHERE name = :searchTerm
// ;";

// $searchPageSubs = [":pageAssocTable", ":termTable", ":termID"];

function searchPageAttrQuery($pageAttr, $tableNames) {
    return "SELECT $pageAttr FROM (" 
            . rtrim(searchPageQuery($tableNames), ";")
            . ") AS atrrpageid LEFT JOIN page USING (pageid)
            ;"
    ;
}

var_dump(getimagesize("C:\Users\gabri\Code\Briel comics website\briel-comics\Supporting documents\NormalizationDiagram.png"));

//add ":pageAttr" to start of array
// $searchPageAttrSubs = [":pageAttr", ...$searchPageSubs]; 

// $tableNameIdx = ["pageAssoc", "names", "id"]
$contwarnTableName = new TableName("contwarningpage", 
                                   "contwarning", 
                                   "contwarningid");
$tagTableName = new TableName("tagpage",
                              "tag",
                              "tagid");

$contwarnIDStatement = $pdoConnection->prepare(searchPageQuery($contwarnTableName));

$contwarnTitleStatement = $pdoConnection->prepare(searchPageAttrQuery("title", $contwarnTableName));

$tagIDStatement = $pdoConnection->prepare(searchPageQuery($tagTableName));

$tagTitleStatement = $pdoConnection->prepare(searchPageAttrQuery("title", $contwarnTableName));

// $tagQuery = 
// "SELECT title FROM (
//  SELECT DISTINCT pageid FROM 
//   tagpage LEFT JOIN tag USING (tagid)
//   WHERE name = :searchTerm
// ) AS tagpageid LEFT JOIN page USING (pageid)
// ;";

echo "Enter search term: ";
$searchTerm = trim(fgets(STDIN));
// var_dump([":searchTerm" => trim(fgets(STDIN))]);
$contwarnIDStatement->execute([":searchTerm" => $searchTerm]);

$row = NULL;
if ( $row = $contwarnIDStatement->fetch(\PDO::FETCH_ASSOC) ) {
    do {
        echo "Content warning: " . $row["pageid"] . "\n";
    } while ($row = $contwarnIDStatement->fetch(\PDO::FETCH_ASSOC));
} else {
    echo "No matching rows found.\n";
}

$tagIDStatement->execute([":searchTerm" => $searchTerm]);

foreach ($tagIDStatement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    echo "Tag: " . $row["pageid"] . "\n";
}

// var_dump($contwarnStatement->fetchAll(PDO::FETCH_ASSOC));

// $statement2 = $pdoConnection->prepare("SELECT * FROM contwarningpage;");
// $statement2->execute();

// var_dump($statement2->fetchAll());

$pdoConnection = null; //Close connection

?>