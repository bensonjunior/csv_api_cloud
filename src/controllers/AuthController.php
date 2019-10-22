<?php

use App\plugins\SecureApi;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use App\models\users\UsersModel;
use Ingenerator\Tokenista;

class AuthController extends Controller
{
    private $request;
    private $response;

    public function __construct()
    {
        new SecureApi();
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
        $this->response->headers->set('content-type', 'application/json');
    }

    public function login()
    {
        if ($this->request->server->get('REQUEST_METHOD') === 'POST') {
            $requestUser = json_decode(file_get_contents('php://input'), true);
            $user = new UsersModel();
            $user = $user->getByUserName($requestUser['user_name']);
            $this->isUserNotFound($user);

            if(password_verify($requestUser['password'],$user->password)){
                unset($user->password);
                $this->response->setContent(json_encode($user));
                $this->response->setStatusCode(201);
                $this->response->send();
            }else{
                $this->response->setContent(json_encode(['Unauthorized']));
                $this->response->setStatusCode(401);
                $this->response->send();
            }
        }
    }

    public function isUserNotFound($user){
        if(empty($user)){
            $this->response->setContent(json_encode(["User Not Found"]));
            $this->response->setStatusCode(404);
            $this->response->send();
            die();
        }
    }

    public function register():void
    {
        if ($this->request->server->get('REQUEST_METHOD') === 'POST') {

            $user = json_decode(file_get_contents('php://input'), true);
            $this->isPasswordSecure($user['password']);
            $this->passwordMatch($user['password'], $user['confirm_password']);
            $this->validateEmail($user['email']);

            $user['create_at'] = date('Y-m-d H:i:s');
            $user['password'] = $this->passwordHasing($user['password']);
            unset($user['confirm_password']);

            $user['role'] = $this->defineUserRole($user);
            $token = new Tokenista('cloudcsv');
            $user['token'] = $token->generate();

            $newUser = new UsersModel();
            $newUser->create($user);

            $this->response->setContent(json_encode(['success']));
            $this->response->setStatusCode(201);
            $this->response->send();
        }
    }

    private function passwordHasing(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2I);
    }

    private function passwordMatch(string $password1, string $password2): void
    {
        if ($password1 != $password2){
            $this->response->setContent(json_encode(['the password not match']));
            $this->response->setStatusCode(409);
            $this->response->send();
            die();
        }
    }

    private function isPasswordSecure(string $password): void
    {
        if (strlen($password) < 8) {
            $this->response->setContent(json_encode(['the password is not secure']));
            $this->response->setStatusCode(409);
            $this->response->send();
            die();
        }
    }

    private function defineUserRole($user):string
    {
        if (isset($user['role'])) {
            if ($user['role'] === 'admin' || $user['role'] === 'user') {
                return $user['role'];
            } else {
                return 'user';
            }
        } else {
            return 'user';
        }
    }

    private function validateEmail($email):void
    {
        $validator = new EmailValidator();
        if($validator->isValid($email, new RFCValidation()) === false){
            $this->response->setContent(json_encode(['the email is not valid']));
            $this->response->setStatusCode(409);
            $this->response->send();
            die();
        }
    }

}