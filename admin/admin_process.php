<?php


include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

class AdminProcess
{
    private $rdb;

    public function __construct($databaseURL)
    {
        $this->rdb = new firebaseRDB($databaseURL);
    }

    public function handle()
    {
        /* RENT ITEMS */
        if(isset($_POST['add_rent_item'])){
            $this->addRentItem();
            return;
        }

        if(isset($_POST['update_rent_item'])){
            $this->updateRentItem();
            return;
        }

        if(isset($_GET['delete_rent_item'])){
            $this->deleteRentItem();
            return;
        }

        /* CASHIER */
        if(isset($_POST['create_cashier'])){
            $this->createCashier();
            return;
        }

        /* KITCHEN */
        if(isset($_POST['action']) &&
            $_POST['action']=="admin_create_kitchen"){
            $this->createKitchen();
            return;
        }

        /* RETURN BOOKING */
        if(isset($_POST['return_booking'])){
            $this->returnBooking();
            return;
        }
    }

    /* ================= IMAGE ================= */

    private function uploadImage($file)
    {
        $dir = __DIR__ . "/item/";

        if(!file_exists($dir)){
            mkdir($dir,0777,true);
        }

        $fileName =
            time() . "_" .
            basename($file["name"]);

        $target = $dir . $fileName;

        if(move_uploaded_file(
            $file["tmp_name"],
            $target
        )){
            return "../admin/item/" . $fileName;
        }

        return "";
    }

    /* ================= ADD RENT ================= */

    private function addRentItem()
    {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);

        $image = "";

        if(!empty($_FILES['image']['name'])){
            $image = $this->uploadImage($_FILES['image']);
        }

        if($name != "" && $price > 0){

            $this->rdb->insert("/rent_items",[
                "name"=>strtolower($name),
                "display_name"=>ucfirst($name),
                "price"=>$price,
                "image"=>$image
            ]);
        }

        header("Location: booking_add.php");
        exit;
    }

    /* ================= UPDATE RENT ================= */

    private function updateRentItem()
    {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);

        $image = $_POST['old_image'] ?? "";

        if(!empty($_FILES['image']['name'])){
            $image = $this->uploadImage($_FILES['image']);
        }

        if($id != "" && $name != "" && $price > 0){

            $this->rdb->update("rent_items",$id,[

                "name"=>strtolower($name),
                "display_name"=>ucfirst($name),
                "price"=>$price,
                "image"=>$image

            ]);
        }

        header("Location: booking_add.php");
        exit;
    }

    /* ================= DELETE RENT ================= */

    private function deleteRentItem()
    {
        $id = $_GET['delete_rent_item'] ?? '';

        if($id != ""){
            $this->rdb->delete("rent_items",$id);
        }

        header("Location: booking_add.php");
        exit;
    }

    /* ================= CREATE CASHIER ================= */

    private function createCashier()
    {
        if(!isset($_SESSION['admin_email'])){
            die("Unauthorized");
        }

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if($full_name=='' || $email=='' || $password==''){
            die("All fields are required.");
        }

        $cashiers = json_decode(
            $this->rdb->retrieve("/cashiers"),
            true
        ) ?? [];

        foreach($cashiers as $c){

            if(
                strtolower($c['email'] ?? '')
                === strtolower($email)
            ){
                die("Email already exists.");
            }
        }

        $this->rdb->insert("/cashiers",[

            "full_name"=>$full_name,
            "email"=>$email,
            "password"=>password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            "created_at"=>date('Y-m-d H:i:s')

        ]);

        header("Location: create_cashier.php?success=1");
        exit;
    }

    /* ================= CREATE KITCHEN ================= */

    private function createKitchen()
    {
        if(!isset($_SESSION['admin_email'])){
            die("Unauthorized");
        }

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if($full_name=='' || $email=='' || $password==''){
            die("All fields are required.");
        }

        $kitchens = json_decode(
            $this->rdb->retrieve("/kitchen"),
            true
        ) ?? [];

        foreach($kitchens as $k){

            if(
                strtolower($k['email'] ?? '')
                === strtolower($email)
            ){
                die("Email already exists.");
            }
        }

        $this->rdb->insert("/kitchen",[

            "full_name"=>$full_name,
            "email"=>$email,
            "password"=>password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            "created_at"=>date('Y-m-d H:i:s')

        ]);

        header("Location: create_kitchen.php?success=1");
        exit;
    }

    /* ================= RETURN BOOKING ================= */

    private function returnBooking()
    {
        $bookingId = $_POST['booking_id'] ?? '';

        if($bookingId==''){
            die("Missing booking ID");
        }

        $bookings = json_decode(
            $this->rdb->retrieve("/bookings"),
            true
        ) ?? [];

        $rent_items = json_decode(
            $this->rdb->retrieve("/rent_items"),
            true
        ) ?? [];

        if(!isset($bookings[$bookingId])){
            die("Booking not found");
        }

        $booking = $bookings[$bookingId];

        if(!empty($booking['items']) &&
            is_array($booking['items'])){

            foreach($booking['items'] as $item){

                $qty = intval($item['qty'] ?? 0);
                $name = strtolower(
                    trim($item['name'] ?? '')
                );

                if($qty<=0 || $name==''){
                    continue;
                }

                foreach($rent_items as $rid=>$ritem){

                    if(
                        strtolower(
                            trim($ritem['name'] ?? '')
                        ) === $name
                    ){

                        $current = intval(
                            $ritem['quantity'] ?? 0
                        );

                        $this->rdb->update(
                            "rent_items",
                            $rid,
                            [
                                "quantity"=>$current+$qty
                            ]
                        );

                        break;
                    }
                }
            }
        }

        $this->rdb->update(
            "bookings",
            $bookingId,
            [

                "status"=>"returned",
                "returned_at"=>date("Y-m-d H:i:s"),
                "returned_by"=>$_SESSION['admin_id'] ?? 'admin'

            ]
        );

        header("Location: booking_reserve.php?success=returned");
        exit;
    }
}

$admin = new AdminProcess($databaseURL);
$admin->handle();

?>