<?php
use App\Factories\X509\MyCompanyX509Generator;
use AndrewSvirin\Ebics\Exceptions\InvalidUserOrUserStateException;
use AndrewSvirin\Ebics\Exceptions\AuthenticationFailedException;
include_once("EBICS_OM/PathFile.php");
include_once("EBICS_OM/Connection.php");
include_once("EBICS_OM/MyCompanyX509Generator.php");

Class ConnectionTests {

    public function testConnectionOrder(string $order, array $args) {
        new PathFile() ;
        $connection = new Connection();

        $code = array(
            'INI' => array(
                'fake' => false
            ),
            'HIA' => array(
                'fake' => false
            ),
            'HPB' => array(
                'fake' => false
            ),
        );

        $x509Generator = new MyCompanyX509Generator ;

        // Incorrect number of parameters
        if (sizeof($args)!=2) {
            throw new LengthException('Incorrect number of parameters');
        }
        else{
            $credentialsID = (int)$args[1];
            // Incorrect value type
            if ($credentialsID==0){
                throw new UnexpectedValueException('Null Value');
            }
            else{
                if (!file_exists(substr(__DIR__, 0, strlen(__DIR__)-6) . '/_data/credentials/credentials_'. $credentialsID .'.json')) {
                    throw new InvalidArgumentException('File does not exist');
                }
            }
        }

     switch ($order) {
        case 'INI':
            return $connection->INIOrder($credentialsID, $code, $x509Generator);
        case 'HIA':
            return $connection->HIAOrder($credentialsID, $code, $x509Generator);
        case 'HPB':
            $connection->HPBOrder($credentialsID, $code, $x509Generator);
        }   

    }


    // Tests the execution of INI, HIA and HPB scripts with an incorrect number of parameters
    public function testInvalidNumberParameter(string $order, array $args) {
        try {
            $this->testConnectionOrder($order, $args);
        }
        catch (LengthException $e) {
            return true;
        }
        return false;
    }


    // Tests the execution of INI, HIA and HPB scripts with an incorrect value in parameters
    public function testIncorrectValueParameter(string $order, array $args) {
        try {
            $this->testConnectionOrder($order, $args);
        }
        catch (UnexpectedValueException $e) {
            return true;
        }
        return false;
    }


    // Tests the execution of INI, HIA and HPB scripts with an file in parameters taht does not exist
    public function testFileNotFoundParameter(string $order, array $args) {
        try {
            $this->testConnectionOrder($order, $args);
        }
        catch (InvalidArgumentException $e) {
            return true;
        }
        return false;
    }


    // Tests the execution of INI, HIA and HPB scripts with an incomplet credential file
    public function testMissValue(string $order, array $args) {
        try {
            $this->testConnectionOrder($order, $args);
        }
        catch (TypeError $e) {
            return true;
        }
        return false;
    }


    // Tests the execution of INI, HIA and HPB scripts with a wrong value in the credential file
    public function testWrongValue(string $order, array $args) {
        try {
            $this->testConnectionOrder($order, $args);
        }
        catch (RuntimeException $e) {
            return 1;
        }
        catch(InvalidUserOrUserStateException $e) {
            return 2;
        }
        return false;
    }


    // Tests the execution of INI and HIA scripts when orders have already been sent
    public function testResendOrder(string $order, array $args) {
        

        $result = $this->testConnectionOrder($order, $args);
        echo "********";
        echo $result;
        if ($result=="091002"){return true;}
        return false;

        // try {
        //     $this->testConnectionOrder($order, $args);
        // }
        // catch (InvalidUserOrUserStateException $e) {
        //     echo "*** 1 exception ****";
        //     return true;
        // }
        // catch(AuthenticationFailedException $e) {
        //     echo "*** 2 exception ****";
        //     return true;
        // }
        // return false;
    }

}

?>