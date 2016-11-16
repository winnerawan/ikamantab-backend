<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));

            $response = array();

            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');

            // validating email address
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You are genius";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }
            // echo json response
            echoRespnse(201, $response);
        });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));

            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();

            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                    $response['createdAt'] = $user['created_at'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }

            echoRespnse(200, $response);
        });


/**
 * Listing all jurusan 
 * method GET
 * url /listJurusan          
 */
$app->get('/listJurusan', function() {
            //global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getListAllJurusan();

            $response["error"] = false;
            $response["jurusan"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["jurusan_id"] = $task["jurusan_id"];
                $tmp["deskripsi"] = $task["deskripsi"];

                array_push($response["jurusan"], $tmp);
            }

            echoRespnse(200, $response);
        });

/**
 * Listing all asrama 
 * method GET
 * url /listAsrama          
 */
$app->get('/listAsrama', function() {
            //global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getListAllAsrama();

            $response["error"] = false;
            $response["asrama"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["asrama_id"] = $task["asrama_id"];
                $tmp["deskripsi"] = $task["deskripsi"];

                array_push($response["asrama"], $tmp);
            }

            echoRespnse(200, $response);
        });


/**
 * Listing all user 
 * method GET
 * url /listUsers          
 */
$app->get('/listUsers', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getListAllUser($user_id);

            $response["error"] = false;
            $response["users"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["name"] = $task["name"];
                $tmp["email"] = $task["email"];
                $tmp["gcm"] = $task["gcm"];
                $tmp["telp"] = $task["telp"];
                $tmp["foto"] = $task["foto"];
                $tmp["angkatan"] = $task["angkatan"];
                $tmp["jurusan"] = $task["jurusan"];

                array_push($response["users"], $tmp);
            }

            echoRespnse(200, $response);
        });




/* * *
 * Updating user
 *  we use this url to update user's gcm registration id
 */
$app->put('/updategcmUser/:id', function($user_id) use ($app) {
    global $app;
 
    verifyRequiredParams(array('gcm_registration_id'));
 
    $gcm_registration_id = $app->request->put('gcm_registration_id');
 
    $db = new DbHandler();
    $response = $db->updateGcmID($user_id, $gcm_registration_id);
 
    echoRespnse(200, $response);
});

/**
 * Listing all info 
 * method GET
 * url /myInformation          
 */
$app->get('/myInformation', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getMyInfo($user_id);

            $response["error"] = false;
            $response["users"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["name"] = $task["name"];
                $tmp["email"] = $task["email"];
                $tmp["gcm"] = $task["gcm"];
                $tmp["foto"] = $task["foto"];
                $tmp["angkatan"] = $task["angkatan"];
                $tmp["jurusan"] = $task["jurusan"];
                $tmp["bio"] = $task["bio"];
                $tmp["profesi"] = $task["profesi"];
                $tmp["keahlian"] = $task["keahlian"];
                $tmp["penghargaan"] = $task["penghargaan"];
                $tmp["minat_profesi"] = $task["minat_profesi"];
                $tmp["referensi_rekomendasi"] = $task["referensi_rekomendasi"];
                $tmp["telp"] = $task["telp"];
                $tmp["jenis_kelamin"] = $task["jenis_kelamin"];
                array_push($response["users"], $tmp);

            }

            echoRespnse(200, $response);
        });



/**
 * Register User Info
 * url - /registerInfo
 * method - POST
 * params - user_id, jenis_kelamin,angkatan_lulus, jurusan_id, asrama_id
 */
$app->post('/registerInfo', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('jenis_kelamin', 'angkatan_lulus', 'jurusan_id', 'asrama_id'));

            $response = array();

            // reading post params
            //$user_id = $app->request->post('user_id');
            $jenis_kelamin = $app->request->post('jenis_kelamin');
            $angkatan_lulus = $app->request->post('angkatan_lulus');
            $jurusan_id = $app->request->post('jurusan_id');
            $asrama_id = $app->request->post('asrama_id');

            // validating email address
            //validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUserInfo($jenis_kelamin, $angkatan_lulus, $jurusan_id, $asrama_id);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You're info successfully registered";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing info";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }
            // echo json response
            echoRespnse(201, $response);
        });


//
//
//
//

/* * *
 * fetching all chat rooms
 */
$app->get('/chat_rooms', 'authenticate', function() {
    $response = array();
    $db = new DbHandler();
 
    // fetching all user tasks
    $result = $db->getAllChatrooms();
 
    $response["error"] = false;
    $response["chat_rooms"] = array();
 
    // pushing single chat room into array
    while ($chat_room = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["chat_room_id"] = $chat_room["chat_room_id"];
        $tmp["name"] = $chat_room["name"];
        $tmp["created_at"] = $chat_room["created_at"];
        array_push($response["chat_rooms"], $tmp);
    }
 
    echoRespnse(200, $response);
});
 
/**
 * Messaging in a chat room
 * Will send push notification using Topic Messaging
 *  */
$app->post('/chat_rooms/:id/message', function($chat_room_id) {
    global $app;
    $db = new DbHandler();
 
    verifyRequiredParams(array('user_id', 'message'));
 
    $user_id = $app->request->post('user_id');
    $message = $app->request->post('message');
 
    $response = $db->addMessage($user_id, $chat_room_id, $message);
 
    if ($response['error'] == false) {
        require_once __DIR__ . '/../libs/gcm/gcm.php';
        require_once __DIR__ . '/../libs/gcm/push.php';
        $gcm = new GCM();
        $push = new Push();
 
        // get the user using userid
        $user = $db->getUser($user_id);
 
        $data = array();
        $data['user'] = $user;
        $data['message'] = $response['message'];
        $data['chat_room_id'] = $chat_room_id;
 
        $push->setTitle("Ikamantab Forum");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_CHATROOM);
        $push->setData($data);
         
        // echo json_encode($push->getPush());exit;
 
        // sending push message to a topic
        $gcm->sendToTopic('topic_' . $chat_room_id, $push->getPush());
 
        $response['user'] = $user;
        $response['error'] = false;
    }
 
    echoRespnse(200, $response);
});
 
 
/**
 * Sending push notification to a single user
 * We use user's gcm registration id to send the message
 * * */
$app->post('/users/:id/message', function($to_user_id) {
    global $app;
    $db = new DbHandler();
 
    verifyRequiredParams(array('message'));
 
    $from_user_id = $app->request->post('user_id');
    $message = $app->request->post('message');
 
    $response = $db->addMessage($from_user_id, $to_user_id, $message);
 
    if ($response['error'] == false) {
        require_once __DIR__ . '/../libs/gcm/gcm.php';
        require_once __DIR__ . '/../libs/gcm/push.php';
        $gcm = new GCM();
        $push = new Push();
 
        $user = $db->getUser($to_user_id);
 
        $data = array();
        $data['user'] = $user;
        $data['message'] = $response['message'];
        $data['image'] = '';
 
        $push->setTitle("Google Cloud Messaging");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_USER);
        $push->setData($data);
 
        // sending push message to single user
        $gcm->send($user['gcm_registration_id'], $push->getPush());
 
        $response['user'] = $user;
        $response['error'] = false;
    }
 
    echoRespnse(200, $response);
});
 
 
/**
 * Sending push notification to multiple users
 * We use gcm registration ids to send notification message
 * At max you can send message to 1000 recipients
 * * */
$app->post('/users/message', function() use ($app) {
 
    $response = array();
    verifyRequiredParams(array('user_id', 'to', 'message'));
 
    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';
 
    $db = new DbHandler();
 
    $user_id = $app->request->post('user_id');
    $to_user_ids = array_filter(explode(',', $app->request->post('to')));
    $message = $app->request->post('message');
 
    $user = $db->getUser($user_id);
    $users = $db->getUsers($to_user_ids);
 
    $registration_ids = array();
 
    // preparing gcm registration ids array
    foreach ($users as $u) {
        array_push($registration_ids, $u['gcm_registration_id']);
    }
 
    // insert messages in db
    // send push to multiple users
    $gcm = new GCM();
    $push = new Push();
 
    // creating tmp message, skipping database insertion
    $msg = array();
    $msg['message'] = $message;
    $msg['message_id'] = '';
    $msg['chat_room_id'] = '';
    $msg['created_at'] = date('Y-m-d G:i:s');
 
    $data = array();
    $data['user'] = $user;
    $data['message'] = $msg;
    $data['image'] = '';
 
    $push->setTitle("Google Cloud Messaging");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_USER);
    $push->setData($data);
 
    // sending push message to multiple users
    $gcm->sendMultiple($registration_ids, $push->getPush());
 
    $response['error'] = false;
 
    echoRespnse(200, $response);
});
 
$app->post('/users/send_to_all', function() use ($app) {
 
    $response = array();
    verifyRequiredParams(array('user_id', 'message'));
 
    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';
 
    $db = new DbHandler();
 
    $user_id = $app->request->post('user_id');
    $message = $app->request->post('message');
 
    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';
    $gcm = new GCM();
    $push = new Push();
 
    // get the user using userid
    $user = $db->getUser($user_id);
     
    // creating tmp message, skipping database insertion
    $msg = array();
    $msg['message'] = $message;
    $msg['message_id'] = '';
    $msg['chat_room_id'] = '';
    $msg['created_at'] = date('Y-m-d G:i:s');
 
    $data = array();
    $data['user'] = $user;
    $data['message'] = $msg;
    $data['image'] = 'http://www.androidhive.info/wp-content/uploads/2016/01/Air-1.png';
 
    $push->setTitle("Google Cloud Messaging");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_USER);
    $push->setData($data);
 
    // sending message to topic `global`
    // On the device every user should subscribe to `global` topic
    $gcm->sendToTopic('global', $push->getPush());
 
    $response['user'] = $user;
    $response['error'] = false;
 
    echoRespnse(200, $response);
});
 
/**
 * Fetching single chat room including all the chat messages
 *  */
$app->get('/chat_rooms/:id', 'authenticate', function($chat_room_id) {
    global $app;
    $db = new DbHandler();
 
    $result = $db->getChatRoom($chat_room_id);
 
    $response["error"] = false;
    $response["messages"] = array();
    $response['chat_room'] = array();
 
    $i = 0;
    // looping through result and preparing tasks array
    while ($chat_room = $result->fetch_assoc()) {
        // adding chat room node
        if ($i == 0) {
            $tmp = array();
            $tmp["chat_room_id"] = $chat_room["chat_room_id"];
            $tmp["name"] = $chat_room["name"];
            $tmp["created_at"] = $chat_room["chat_room_created_at"];
            $response['chat_room'] = $tmp;
        }
 
        if ($chat_room['user_id'] != NULL) {
            // message node
            $cmt = array();
            $cmt["message"] = $chat_room["message"];
            $cmt["message_id"] = $chat_room["message_id"];
            $cmt["created_at"] = $chat_room["created_at"];
 
            // user node
            $user = array();
            $user['user_id'] = $chat_room['user_id'];
            $user['username'] = $chat_room['username'];
            $cmt['user'] = $user;
 
            array_push($response["messages"], $cmt);
        }
    }
 
    echoRespnse(200, $response);
});


/**
 * 
 * 
 * 
 * 
 */
$app->get('/myInfo', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getMyInfo($user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["name"] = $result["name"];
                $response["foto"] = $result["foto"];
                $response["angkatan"] = $result["angkatan"];
                $response["jurusan"] = $result["jurusan"];
                $response["bio"] = $result["bio"];
                $response["profesi"] = $result["profesi"];
                $response["keahlian"] = $result["keahlian"];
                $response["penghargaan"] = $result["penghargaan"];
                $response["minat_profesi"] = $result["minat_profesi"];
                $response["referensi_rekomendasi"] = $result["referensi_rekomendasi"];
                $response["telp"] = $result["telp"];
                $response["jenis_kelamin"] = $result["jenis_kelamin"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });


/*
 * ------------------------ METHODS WITH AUTHENTICATION ------------------------
 */

/**
 * Listing all tasks of particual user
 * method GET
 * url /tasks          
 */
$app->get('/tasks', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getAllUserTasks($user_id);

            $response["error"] = false;
            $response["tasks"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["task"] = $task["task"];
                $tmp["status"] = $task["status"];
                $tmp["createdAt"] = $task["created_at"];
                array_push($response["tasks"], $tmp);
            }

            echoRespnse(200, $response);
        });

/**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/tasks/:id', 'authenticate', function($task_id) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["task"] = $result["task"];
                $response["status"] = $result["status"];
                $response["createdAt"] = $result["created_at"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/user/:id', 'authenticate', function($user_id) {
            //global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getShareInfo($user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["name"] = $result["name"];
                $response["email"] = $result["email"];
                $response["foto"] = $result["foto"];
                $response["jenis_kelamin"] = $result["jenis_kelamin"];
                $response["angkatan"] = $result["angkatan"];
                $response["jurusan"] = $result["jurusan"];
                $response["asrama"] = $result["asrama"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });


/**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/userid/:email', function($email) {
            //global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getIdByEmail($email);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["email"] = $result["email"];

                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Creating new task in db
 * method POST
 * params - name
 * url - /tasks/
 */
$app->post('/tasks', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('task'));

            $response = array();
            $task = $app->request->post('task');

            global $user_id;
            $db = new DbHandler();

            // creating new task
            $task_id = $db->createTask($user_id, $task);

            if ($task_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Task created successfully";
                $response["task_id"] = $task_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create task. Please try again";
                echoRespnse(200, $response);
            }            
        });



/**
 * Updating existing task
 * method PUT
 * params task, status
 * url - /tasks/:id
 */
$app->put('/updateProfesi', 'authenticate', function() use($app) {
            // check for required params
            //verifyRequiredParams(array('profesi'));

            global $user_id;            
            $profesi = $app->request->put('profesi');
            //$status = $app->request->put('status');

            $db = new DbHandler();
            $response = array();

            // updating task
            $result = $db->updateProfesi($user_id, $profesi);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Task updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Task failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });


/**
 * Updating existing task
 * method PUT
 * params task, status
 * url - /tasks/:id
 */
$app->put('/tasks/:id', 'authenticate', function($task_id) use($app) {
            // check for required params
            verifyRequiredParams(array('task', 'status'));

            global $user_id;            
            $task = $app->request->put('task');
            $status = $app->request->put('status');

            $db = new DbHandler();
            $response = array();

            // updating task
            $result = $db->updateTask($user_id, $task_id, $task, $status);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Task updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Task failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * Deleting task. Users can delete only their tasks
 * method DELETE
 * url /tasks
 */
$app->delete('/tasks/:id', 'authenticate', function($task_id) use($app) {
            global $user_id;

            $db = new DbHandler();
            $response = array();
            $result = $db->deleteTask($user_id, $task_id);
            if ($result) {
                // task deleted successfully
                $response["error"] = false;
                $response["message"] = "Task deleted succesfully";
            } else {
                // task failed to delete
                $response["error"] = true;
                $response["message"] = "Task failed to delete. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>