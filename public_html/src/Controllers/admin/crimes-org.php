<?PHP

use src\Business\AdminService;
use src\Business\Logic\admin\Pagination;

require_once __DIR__ . '/.inc.head.php';

$table = new AdminService("crime_org");
$pagination = new Pagination("crime_org", $table);
$crimes = $table->getTableRows($pagination->from, $pagination->to);

require_once __DIR__ . '/.inc.foot.php';
$twigVars['crimes'] = $crimes;
$twigVars['pagination'] = $pagination;

echo $twig->render('/src/Views/admin/crimes-org.twig', $twigVars);
