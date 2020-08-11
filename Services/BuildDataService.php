<?php


namespace AMM\SymfonyDynamicDataTableBundle\Services;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class BuildDataService
{
    private $object;
    private $template;
    private $em;
    private $qb;
    private $joined;
    private $alias;
    private $renders = null;

    public function __construct($twig, EntityManagerInterface $em)
    {
        $this->twig = $twig;
        $this->em = $em;

    }

    /**
     * @param string $object Namespace of the object
     * @param string $template Path to twig template for HTML formating of your data (see template.html.twig for exemple)
     * @param string $columns all the columns in the datatables
     */
    public function set(string $object, string $template, string $columns, $renders = null)
    {
        if ($renders != null)
            $this->renders = $renders;

        $obj = new $object;
        $objName = explode("\\", get_class($obj));
        if (is_object($obj)) {
            $this->object = $object;
            $this->template = $template;
            $this->alias = strtolower($objName[count($objName) - 1]);
            if (method_exists($obj, "getAlias")) {
                $this->alias = $obj->getAlias();
            }
            $this->qb = $this->em->getRepository($object)->createQueryBuilder($this->alias);
            $columnSplit = explode(",", $columns);
            $joined = [];

            //Adds all the left join necessery
            for ($i = 0; $i < count($columnSplit); $i++) {

                $col = explode(".", $columnSplit[$i]);

                if (count($col) > 1) {

                    if (!in_array($col[0], $joined)) {
                        $this->qb->leftJoin($this->alias . "." . $col[0], $col[0])
                            ->addSelect($col[0]);
                        array_push($joined, $col[0]);
                    }

                    for ($x = 1; $x < count($col) - 1; $x++) {
                        $suffix = $col[0] .".";
                        for($y = 1; $y < $x; $y++) {
                            $suffix .= $col[$y] . ".";
                        }


                        if (!in_array($suffix .$col[$x], $joined)) {
                            $this->qb->leftJoin($suffix . $col[$x], $col[$x])
                                ->addSelect($col[$x]);
                            array_push($joined, $suffix. $col[$x - 1]);
                        }


                    }
                }
            }
            $this->joined = $joined;
        }
    }

    /**
     * @param Request $request The request sent by your DataTable
     * @return QueryBuilder Returns a query on which you can add parameters
     */
    public function getBasicQuery(Request $request)
    {
        //Check to see if the set has been made
        if ($this->object !== null && $this->template !== null && $this->em !== null && $this->qb !== null) {
            //Builds a reflection class to list all properties
            $reflect = new ReflectionClass($this->object);

            if ($request->getMethod() == 'POST') {
                $start = $request->request->get('iDisplayStart');
                $length = $request->request->get('iDisplayLength');
                $sortCol = $request->request->get('iSortCol_0');
                $columns = $request->request->get('sColumns');
                $singleSearch = $request->request->get('sSearch');
                $sortDir = $request->request->get("sSortDir_0");
            } else
                throw new Exception('Invalid request received.');;

            //Array with all the columns currently in the DataTable
            $colName = explode(',', $columns);
            $this->colName = $colName;
            $colSearch = [];

            //Check to see if single search or multisearch
            if ($singleSearch) {
                //Applies the search to all the columns
                $i = 0;
                foreach ($colName as $col) {
                    array_push($colSearch, [$col, $singleSearch]);
                    $i++;
                }
            } else {
                //Gets the searching parameters from all the search bars
                $i = 0;
                foreach ($colName as $col) {
                    array_push($colSearch, [$col, $request->request->get('sSearch_' . $i)]);
                    $i++;
                }
            }

            //Checks all the colomns to see by which one it is sorted and then gets the direction
            $sortIndex = intval($sortCol);
            $sortColname = $colName[$sortCol];
//            $sortDir = $sort = $request->request->get('sSortDir_' . ($sortIndex+1));

            //Gets the data
            $result = $this->applyParameters($start, $length, $sortColname, $sortDir, $colSearch, $reflect, $singleSearch == "");

            return $result;
        } else {
            throw new Exception('The service hasn\'t been set properly.');
        }
    }


    private function applyParameters($start, $length, $sortColname, $sortDir, $colSearch, ReflectionClass $objectClass, $isMultiSearch)
    {
        //Get all the properties to loop through
        $classProperties = $objectClass->getProperties();
        $classProp = [];

        foreach ($classProperties as $property) {
            array_push($classProp, $property->name);
        }


        //Initiliasing the querryBuilder
        /**
         * @var $query QueryBuilder\
         */
        $query = clone $this->qb;
        $searchIndex = 0;
        //Loops the properties to apply the searches made by the the user to the queryBuilder
        foreach ($colSearch as $key => $column) {
            //Check if the search parameter is searchable (is a property of the entity/sub-entity or is a dummy field
            if (count(explode(".", $column[0])) > 1 || in_array($column[0], $classProp)) {

                $searchQuery = null;
                $colSplit = explode(".", $column[0]);
                $isConcat =false;

                //Check if the search parameter is a property from the class or a sub-entity
                if ($column[1] !== "" && $column[0] !== "" && count($colSplit) === 1) {
                    $prefix = $this->alias . "." . $column[0];
                    if(count(explode("-",$column[0]))>1) {
                        $isConcat = true;
                        $strReplace = str_replace("-","",$column[0]);
                        $properties = explode("-",$column[0]);
                        $addSelect="CONCAT(";
                        foreach ($properties as $prop){
                            $addSelect .= $this->alias.".".$prop .",";
                        }
                        $addSelect = trim($prefix,",");
                        $addSelect .= ")";
                        $prefix = $addSelect;
                    }
                    $searchQuery = $prefix . ' LIKE :search' . $searchIndex;
                } else if ($column[1] !== "" && $column[0] !== "") {

                    $prefix = $colSplit[count($colSplit) - 2] . "." . $colSplit[count($colSplit) - 1];
                    if(count(explode("-",$colSplit[count($colSplit) - 1]))>1) {
                        $isConcat = true;
                        $strReplace = str_replace("-","",$colSplit[count($colSplit) - 1]);
                        $properties = explode("-",$colSplit[count($colSplit) - 1]);
                        $addSelect="CONCAT(";
                        for ($i =0;$i<count($properties);$i++){
                            $addSelect .= $colSplit[count($colSplit) - 2]."." .$properties[$i].(($i === count($properties)-1)?"":",");
                        }
                        $addSelect .= ")";
                        $prefix = $addSelect;
                    }
                    $searchQuery = $prefix . ' LIKE :search' . $searchIndex;
                }

                if ($searchQuery !== null) {

                    if ($isMultiSearch) {
                        $query->andWhere($searchQuery)
                            ->setParameter("search" . $searchIndex, '%' . $column[1] . '%');

                    } else {
                        $query->orWhere($searchQuery)
                            ->setParameter("search" . $searchIndex, '%' . $column[1] . '%');

                    }
                }
                $searchIndex++;
            }
        }

        //Only gets the needed number of data
        $query->setFirstResult($start)->setMaxResults($length);
        $sortProp = explode(".",$sortColname);
        if(count($sortProp)>1)
            $sortProp = $sortProp[count($sortProp)-2];
        if ($sortDir !== null && $sortProp !== null && (in_array($sortProp, $classProp) || in_array($sortColname,$classProp))) {
            if (count(explode(".", $sortColname)) > 1) {
                $query->orderBy($sortColname, strtoupper($sortDir));
            } else {
                $query->orderBy($this->alias . "." . $sortColname, strtoupper($sortDir));
            }
        }

        return $query;
    }

    /**
     * @param QueryBuilder $query your finalized query
     * @return mixed Returns the data in a json format
     */
    public function renderData($query)
    {
        //Gets the objects's repository
        $repository = $this->em->getRepository($this->object);

        //Remove the parameters that we dont need
        $countQuery = clone $query;
        $countQuery->resetDQLPart('orderBy');
        $countQuery->setFirstResult(0);
        $countQuery->setMaxResults(null);
        $countQuery->select('COUNT(' . $this->alias . '.id)');

        $results = $query->getQuery()->getResult();
        $countResult = $countQuery->getQuery()->getSingleScalarResult();

        //Render twig to build the data in a json format
        //also alows the user to modify how it will look in the front-end
        if ($this->renders != null) {

            $properties = array('input' => $results, 'properties' => $this->colName);

            foreach ($this->renders as $key => $value) {
                $properties[$key] = $value;
            }

            $twigresponse = $this->twig->render(
                $this->template,
                $properties
            );

        } else {
            $twigresponse = $this->twig->render(
                $this->template,
                array('input' => $results, 'properties' => $this->colName)
            );
        }

        $response = '{
            "recordsFiltered": ' . $countResult . ',
            "data": ' . $twigresponse . '}';

        return $response;

    }

}