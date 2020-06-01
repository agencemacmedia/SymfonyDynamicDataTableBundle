<?php


namespace AMM\SymfonyDynamicDataTableBundle\Services;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use OC\PlatformBundle\Entity\Advert;
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
    private $className;

    public function __construct($twig,EntityManagerInterface $em)
    {
        $this->twig = $twig;
        $this->em = $em;

    }

    /**
     * @param string $object Namespace of the object
     * @param string $template Path to twig template for HTML formating of your data (see template.html.twig for exemple)
     */
    public function set(string $object,string $template)
    {
        $obj = new $object;
        $objName = explode("\\" ,get_class($obj));
        if(is_object($obj)) {
            $this->object = $object;
            $this->template = $template;
            $this->className = strtolower($objName[count($objName)-1]);
            $this->qb = $this->em->getRepository($object)->createQueryBuilder($this->className);
        }
    }
    /**
     * @param Request $request The request sent by your DataTable
     * @return QueryBuilder Returns a query on which you can add parameters
     */
    public function getBasicQuery(Request $request)
    {
        //Check to see if the set has been made
        if($this->object !== null && $this->template !== null&& $this->em !== null && $this->qb !== null)
        {
            //Builds a reflection class to list all properties
            $reflect = new ReflectionClass($this->object);

            if ($request->getMethod() == 'POST') {
                $start = $request->request->get('iDisplayStart');
                $length = $request->request->get('iDisplayLength');
                $sortCol = $request->request->get('iSortingCols');
                $columns = $request->request->get('sColumns');
                $singleSearch = $request->request->get('sSearch');
            } else
                throw new Exception('Invalid request received.');;

            //Array with all the columns currently in the DataTable
            $colName = explode(',', $columns);
            $this->colName = $colName;
            $colSearch =[];

            //Check to see if single search or multisearch
            if($singleSearch)
            {
                //Applies the search to all the columns
                $i = 0;
                foreach ($colName as $col) {
                    array_push($colSearch, [$col, $singleSearch]);
                    $i++;
                }
            }else {
                //Gets the searching parameters from all the search bars
                $i = 0;
                foreach ($colName as $col) {
                    array_push($colSearch, [$col, $request->request->get('sSearch_' . $i)]);
                    $i++;
                }
            }

            //Checks all the colomns to see by which one it is sorted and then gets the direction
            $sortIndex = intval($sortCol) - 1;
            $sortColname = $colName[$sortIndex];
            $sortDir = $sort = $request->request->get('sSortDir_'.$sortIndex);;

            //Gets the data
            $result = $this->applyParameters($start, $length, $sortColname, $sortDir, $colSearch, $reflect,is_null($singleSearch));

            return $result;
        }else{
            throw new Exception('The service hasn\'t been set properly.');
        }
    }

    private function applyParameters($start, $length,$sortColname,$sortDir,$colSearch,ReflectionClass  $objectClass,$isMultiSearch)
    {
        //Get all the properties to loop through
        $classProperties = $objectClass->getProperties();

        //Initiliasing the querryBuilder
        $query = clone $this->qb;

        //Loops the properties to apply the searches made by the the user to the queryBuilder
        foreach ($classProperties as $property)
        {
            foreach ($colSearch as $key => $column)
            {
                $searchQuery = null;
                if($column[0] === $property->getName())
                {
                    if($column[1]!== "")
                    {
                        $searchQuery = $this->className ."." .$property->getName() . ' LIKE \'%' .$column[1] .'%\'';
                    }
                }
                if ($searchQuery !== null) {

                    if($isMultiSearch) {

                        $query->andWhere($searchQuery);

                    }else{

                        $query->orWhere($searchQuery);

                    }
                }
            }
        }

        //Only gets the needed number of data
        $query->setFirstResult($start)->setMaxResults($length);

        if ($sortDir !== null && $sortColname !== null) {
            $query->orderBy($this->className.".".$sortColname, $sortDir);
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
        $countQuery->select('COUNT('.$this->className.')');
		
        $results = $query->getQuery()->getResult();
        $countResult = $countQuery->getQuery()->getSingleScalarResult();

        $total_objects_count = $repository->count([]);

        //Render twig to build the data in a json format
        //also alows the user to modify how it will look in the front-end
        $twigresponse = $this->twig->render(
            $this->template,
            array('input'=>$results,'properties' => $this->colName)
        );

        $response = '{
            "recordsTotal": ' . $total_objects_count . ',
            "recordsFiltered": ' . $countResult . ',
            "data": ' . $twigresponse . '}';

        return $response;

    }

}
