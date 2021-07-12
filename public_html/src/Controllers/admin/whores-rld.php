<?PHP

use src\Business\AdminService;
use src\Business\Logic\admin\Pagination;

require_once __DIR__ . '/.inc.head.php';

if($member->getStatus() > 2) $route->headTo('admin');

$table = new AdminService("rld_whore");
$pagination = new Pagination("rld_whore", $table);
$whoresRLD = $table->getTableRows($pagination->from, $pagination->to);

require_once __DIR__ . '/.inc.foot.php';
$twigVars['whoresRLD'] = $whoresRLD;
$twigVars['pagination'] = $pagination;

echo $twig->render('/src/Views/admin/whores-rld.twig', $twigVars);
