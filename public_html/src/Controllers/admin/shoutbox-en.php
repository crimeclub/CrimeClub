<?PHP

use src\Business\AdminService;
use src\Business\Logic\admin\Pagination;

require_once __DIR__ . '/.inc.head.php';

if($member->getStatus() > 2) $route->headTo('admin');

$table = new AdminService("shoutbox_en");
$pagination = new Pagination("shoutbox_en", $table);
$shoutbox = $table->getTableRows($pagination->from, $pagination->to);

require_once __DIR__ . '/.inc.foot.php';
$twigVars['shoutbox_en'] = $shoutbox;
$twigVars['pagination'] = $pagination;

echo $twig->render('/src/Views/admin/shoutbox-en.twig', $twigVars);
