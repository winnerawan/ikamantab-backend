<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /* ------------- `users` table method ------------------ */

    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = array();

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateApiKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(name, email, password_hash, api_key, status) values(?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $password_hash, $api_key);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }


/**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }


    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // $api_key = $stmt->get_result()->fetch_assoc();
            // TODO
            $stmt->bind_result($api_key);
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }




    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }




    // updating user GCM registration ID
    public function updateGcmID($user_id, $gcm_registration_id) {
        $response = array();
        $stmt = $this->conn->prepare("UPDATE users SET gcm_registration_id = ? WHERE id = ?");
        $stmt->bind_param("si", $gcm_registration_id, $user_id);
 
        if ($stmt->execute()) {
            // User successfully updated
            $response["error"] = false;
            $response["message"] = 'GCM registration ID updated successfully';
        } else {
            // Failed to update user
            $response["error"] = true;
            $response["message"] = "Failed to update GCM registration ID";
            $stmt->error;
        }
        $stmt->close();
 
        return $response;
    }


    

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /* ------------- `tasks` table method ------------------ */

    /**
     * Creating new task
     * @param String $user_id user id to whom task belongs to
     * @param String $task task text
     */
    public function createTask($user_id, $task) {
        $stmt = $this->conn->prepare("INSERT INTO tasks(task) VALUES(?)");
        $stmt->bind_param("s", $task);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // task row created
            // now assign the task to user
            $new_task_id = $this->conn->insert_id;
            $res = $this->createUserTask($user_id, $new_task_id);
            if ($res) {
                // task created successfully
                return $new_task_id;
            } else {
                // task failed to create
                return NULL;
            }
        } else {
            // task failed to create
            return NULL;
        }
    }

    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getTask($task_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT t.id, t.task, t.status, t.created_at from tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["task"] = $task;
            $res["status"] = $status;
            $res["created_at"] = $created_at;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching all user tasks
     * @param String $user_id id of the user
     */
    public function getAllUserTasks($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM tasks t, user_tasks ut WHERE t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching all pending friend request
     * @param String $user_id id of the user
     */
    public function getPendingFriendRequest($user_id) {
        $stmt = $this->conn->prepare("SELECT f1.friend_id AS friend_id FROM friendship AS f1 LEFT JOIN friendship AS f2 ON f1.friend_id = f2.friend_id AND f1.user_id = f2.friend_id WHERE f1.accepted = 0 AND f1.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching all pending friend request
     * @param String $user_id id of the user
     */
    public function getFriendRequest($user_id) {
        $stmt = $this->conn->prepare("SELECT users.id, users.name, users.email, users.gcm_registration_id as gcm, users.created_at, user_detail.telp, user_detail.jenis_kelamin , user_detail.angkatan_lulus as angkatan, user_detail.foto, jurusan.deskripsi as jurusan, asrama.deskripsi as asrama FROM friendship f INNER JOIN users ON users.id=f.user_id INNER JOIN user_detail ON user_detail.user_id=users.id INNER JOIN asrama ON user_detail.asrama_id = asrama.asrama_id INNER JOIN jurusan ON user_detail.jurusan_id = jurusan.jurusan_id WHERE f.friend_id= ? AND f.accepted = 0 ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching all pending friend request
     * @param String $user_id id of the user
     */
    public function suggestionFriend($user_id) {
        $stmt = $this->conn->prepare("SELECT F2.friend_id, users.name, users.email, users.gcm_registration_id as gcm, users.created_at, user_detail.angkatan_lulus as angkatan, jurusan.deskripsi as jurusan, user_detail.asrama_id as asrama , user_detail.jenis_kelamin, user_detail.foto, user_detail.telp FROM friendship F JOIN friendship F2 ON F.friend_id = F2.user_id JOIN users ON users.id = F2.friend_id JOIN user_detail ON user_detail.user_id = F2.friend_id INNER JOIN jurusan ON jurusan.jurusan_id = user_detail.jurusan_id INNER JOIN asrama ON asrama.asrama_id = user_detail.asrama_id WHERE F2.friend_id NOT IN (SELECT friend_id FROM friendship WHERE user_id=?) AND F.user_id = ? AND f2.friend_id != ? ");
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Updating task
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateTask($user_id, $task_id, $task, $status) {
        $stmt = $this->conn->prepare("UPDATE tasks t, user_tasks ut set t.task = ?, t.status = ? WHERE t.id = ? AND t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $task, $status, $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /**
     * Deleting a task
     * @param String $task_id id of the task to delete
     */
    public function deleteTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("DELETE t FROM tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /* ------------- `user_tasks` table method ------------------ */

    /**
     * Function to assign a task to user
     * @param String $user_id id of the user
     * @param String $task_id id of the task
     */
    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_tasks(user_id, task_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $task_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }

    /**
     * Function to add friend
     * @param String $user_id id of the user
     * @param String $friend id of the friend
     */
    public function addFriend($user_id, $friend_id) {
        $stmt = $this->conn->prepare("INSERT INTO friendship (user_id, friend_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $friend_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }


/**
     * Function to add friend
     * @param String $user_id id of the user
     * @param String $friend id of the friend
     */
    public function updateAccept($user_id, $friend_id) {
        $stmt = $this->conn->prepare("UPDATE friendship SET accepted = 1 WHERE user_id = ? AND friend_id = ? ");
        $stmt->bind_param("ii", $user_id, $friend_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }

    /**
     * Function to add friend
     * @param String $user_id id of the user
     * @param String $friend id of the friend
     */
    public function acceptFriend($user_id, $friend_id) {
        $stmt = $this->conn->prepare("INSERT INTO friendship (user_id, friend_id, accepted) values(?, ?, 1)");
        $stmt->bind_param("ii", $user_id, $friend_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }


/**
     * Updating bio
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateBio($user_id, $bio) {
        $stmt = $this->conn->prepare("UPDATE user_detail set bio = ? WHERE user_id= ?");
        $stmt->bind_param("si", $bio, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }


	/**
     * Updating profesi
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateProfesi($user_id, $profesi) {
        $stmt = $this->conn->prepare("UPDATE user_detail set profesi= ? WHERE user_id= ?");
        $stmt->bind_param("si", $profesi, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /**
     * Updating pelatihan / keahlian
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateKeahlian($user_id, $keahlian) {
        $stmt = $this->conn->prepare("UPDATE user_detail set keahlian = ? WHERE user_id= ?");
        $stmt->bind_param("si", $keahlian, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

        /**
     * Updating penghargaan
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updatePenghargaan($user_id, $penghargaan) {
        $stmt = $this->conn->prepare("UPDATE user_detail set penghargaan = ? WHERE user_id= ?");
        $stmt->bind_param("si", $penghargaan, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

        /**
     * Updating minat_profesi
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateMinat($user_id, $minat_profesi) {
        $stmt = $this->conn->prepare("UPDATE user_detail set minat_profesi = ? WHERE user_id= ?");
        $stmt->bind_param("si", $minat_profesi, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }


        /**
     * Updating minat_profesi
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateReferensi($user_id, $referensi_rekomendasi) {
        $stmt = $this->conn->prepare("UPDATE user_detail set referensi_rekomendasi = ? WHERE user_id= ?");
        $stmt->bind_param("si", $referensi_rekomendasi, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
    

            /**
     * Updating minat_profesi
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateTelp($user_id, $telp) {
        $stmt = $this->conn->prepare("UPDATE user_detail set telp = ? WHERE user_id= ?");
        $stmt->bind_param("si", $telp, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }


            /**
     * Updating minat_profesi
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateEmail($id, $email) {
        $stmt = $this->conn->prepare("UPDATE users set email = ? WHERE id= ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /**
     * Fetching list user != user
     * @param String $user_id id of the user
     */
    public function getListAllUser($user_id) {
        $stmt = $this->conn->prepare("SELECT users.id,users.name,users.email, users.gcm_registration_id as gcm, user_detail.bio, user_detail.profesi, user_detail.keahlian, user_detail.penghargaan, user_detail.minat_profesi, user_detail.referensi_rekomendasi, user_detail.foto, user_detail.telp, user_detail.angkatan_lulus as angkatan,jurusan.deskripsi as jurusan, user_detail.jenis_kelamin, asrama.deskripsi as asrama FROM users INNER JOIN user_detail on users.id=user_detail.user_id INNER JOIN jurusan on user_detail.jurusan_id=jurusan.jurusan_id INNER JOIN asrama ON asrama.asrama_id = user_detail.asrama_id and user_detail.user_id != ? ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching list friends of user
     * @param null
     * Authorization
     */

    public function getListAllFriends($user_id) {
        $stmt = $this->conn->prepare("SELECT u.id, u.name, u.email, u.gcm_registration_id as gcm, u.created_at, ud.angkatan_lulus as angkatan, ud.jenis_kelamin, asrama.deskripsi as asrama, jurusan.deskripsi as jurusan, ud.bio, ud.profesi, ud.keahlian, ud.penghargaan, ud.minat_profesi, ud.referensi_rekomendasi, ud.foto, ud.telp FROM friendship fs INNER JOIN users u ON (u.id = fs.friend_id) INNER JOIN user_detail ud ON ud.user_id=u.id INNER JOIN asrama ON asrama.asrama_id=ud.asrama_id INNER JOIN jurusan ON jurusan.jurusan_id=ud.jurusan_id WHERE fs.accepted = 1 AND fs.user_id = ? ORDER BY u.name");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching list jurusan
     * @param null
     */
    public function getListAllJurusan() {
        $stmt = $this->conn->prepare("SELECT * FROM jurusan");
        //$stmt->bind_param();
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    /**
     * Fetching list asrama
     * @param null
     */
    public function getListAllAsrama() {
        $stmt = $this->conn->prepare("SELECT * FROM asrama");
        //$stmt->bind_param();
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }

    /**
     * Fetching user info
     * @param String $user_id id of the user
     
    public function getMyInfo($user_id) {
        $stmt = $this->conn->prepare("SELECT users.id,users.name,users.email,users.gcm_registration_id as gcm, user_detail.foto,user_detail.bio,user_detail.profesi,user_detail.keahlian,user_detail.penghargaan,user_detail.minat_profesi,user_detail.referensi_rekomendasi,user_detail.telp,user_detail.jenis_kelamin,user_detail.angkatan_lulus as angkatan,jurusan.deskripsi as jurusan FROM users INNER JOIN user_detail on users.id=user_detail.user_id INNER JOIN jurusan on user_detail.jurusan_id=jurusan.jurusan_id and user_detail.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }
*/
     /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getMyInfo($user_id) {
        $stmt = $this->conn->prepare("SELECT users.id,users.name,users.email,users.gcm_registration_id as gcm, user_detail.foto,user_detail.bio,user_detail.profesi,user_detail.keahlian,user_detail.penghargaan,user_detail.minat_profesi,user_detail.referensi_rekomendasi,user_detail.telp,user_detail.jenis_kelamin,user_detail.angkatan_lulus as angkatan,jurusan.deskripsi as jurusan FROM users INNER JOIN user_detail on users.id=user_detail.user_id INNER JOIN jurusan on user_detail.jurusan_id=jurusan.jurusan_id and user_detail.user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $name, $email, $gcm, $foto, $bio, $profesi, $keahlian, $penghargaan, $minat_profesi, $referensi_rekomendasi, $telp, $jenis_kelamin, $angkatan, $jurusan);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["name"] = $name;
            $res["email"] = $email;
            $res["gcm"] = $gcm;
            $res["foto"] = $foto;
            $res["bio"] = $bio;
            $res["profesi"] = $profesi;
            $res["keahlian"] = $keahlian;
            $res["penghargaan"] = $penghargaan;
            $res["minat_profesi"] = $minat_profesi;
            $res["referensi_rekomendasi"] = $referensi_rekomendasi;
            $res["telp"] = $telp;
            $res["jenis_kelamin"] = $jenis_kelamin;
            $res["angkatan"] = $angkatan;
            $res["jurusan"] = $jurusan;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

     /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getShareInfo($user_id) {
        $stmt = $this->conn->prepare("SELECT users.id,users.name, users.email, user_detail.foto,user_detail.jenis_kelamin, user_detail.telp, user_detail.angkatan_lulus as angkatan,jurusan.deskripsi as jurusan, asrama.deskripsi as asrama FROM users INNER JOIN user_detail on users.id=user_detail.user_id INNER JOIN jurusan on user_detail.jurusan_id=jurusan.jurusan_id INNER JOIN asrama on user_detail.asrama_id=asrama.asrama_id and user_detail.user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $name, $email, $foto, $jenis_kelamin, $telp, $angkatan, $jurusan, $asrama);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["name"] = $name;
            $res["email"] = $email;
            $res["foto"] = $foto;
            $res["jenis_kelamin"] = $jenis_kelamin;
            $res["telp"] = $telp;
            $res["angkatan"] = $angkatan;
            $res["jurusan"] = $jurusan;
            $res["asrama"] = $asrama;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getIdByEmail($email) {
        $stmt = $this->conn->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $email);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["email"] = $email;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function hasFriends($user_id, $friend_id) {
        $stmt = $this->conn->prepare("SELECT user_id FROM friendship WHERE user_id = ? AND friend_id = ? AND accepted = 1");
        $stmt->bind_param("ii", $user_id, $friend_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($user_id);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["user_id"] = $user_id;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }


    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function hasAdded($user_id, $friend_id) {
        $stmt = $this->conn->prepare("SELECT user_id FROM friendship WHERE user_id = ? AND friend_id = ? AND accepted = 0");
        $stmt->bind_param("ii", $user_id, $friend_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($user_id);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["user_id"] = $user_id;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }


     /**
     * 
     * 
     * 
     */
    public function updateUserAngkatan($user_id, $angkatan_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_detail(user_id, angkatan_id) values(?, ?) ON DUPLICATE KEY UPDATE angkatan_id= ?");
        $stmt->bind_param("ii", $user_id, $angkatan_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }


    /**
     * Creating new user info
     * @param String $name User full name
     * @param String $email User login email id
     * @param user_id, jenis_kelamin,angkatan_lulus, jurusan_id, asrama_id
     */
    public function createUserInfo($jenis_kelamin, $angkatan_lulus, $jurusan_id, $asrama_id) {
        //require_once 'PassHash.php';
        $response = array();

        // First check if user already existed in db
    
            // insert query
            $stmt = $this->conn->prepare("INSERT INTO user_detail(user_id, jenis_kelamin, angkatan_lulus, jurusan_id, asrama_id) values((SELECT COUNT(LAST_INSERT_ID()) FROM users), ?, ?, ?, ?)");
            $stmt->bind_param("ssss", $jenis_kelamin, $angkatan_lulus, $jurusan_id, $asrama_id);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
    

        return $response;
    }

    // fetching all chat rooms
    public function getAllChatrooms() {
        $stmt = $this->conn->prepare("SELECT * FROM chat_rooms");
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }


    // fetching single chat room by id
    function getChatRoom($chat_room_id) {
        $stmt = $this->conn->prepare("SELECT cr.chat_room_id, cr.name, cr.created_at as chat_room_created_at, u.name as username, c.* FROM chat_rooms cr LEFT JOIN messages c ON c.chat_room_id = cr.chat_room_id LEFT JOIN users u ON u.id = c.user_id WHERE cr.chat_room_id = ?");
        $stmt->bind_param("i", $chat_room_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }

    // fetching single user by id
    public function getUser($id) {
        $stmt = $this->conn->prepare("SELECT id, name, email, gcm_registration_id, created_at FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($id, $name, $email, $gcm_registration_id, $created_at);
            $stmt->fetch();
            $user = array();
            $user["id"] = $id;
            $user["name"] = $name;
            $user["email"] = $email;
            $user["gcm_registration_id"] = $gcm_registration_id;
            $user["created_at"] = $created_at;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    // fetching multiple users by ids
    public function getUsers($ids) {
 
        $users = array();
        if (sizeof($user_ids) > 0) {
            $query = "SELECT id, name, email, gcm_registration_id, created_at FROM users WHERE id IN (";
 
            foreach ($ids as $id) {
                $query .= $id . ',';
            }
 
            $query = substr($query, 0, strlen($query) - 1);
            $query .= ')';
 
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
 
            while ($user = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $user['id'];
                $tmp["name"] = $user['name'];
                $tmp["email"] = $user['email'];
                $tmp["gcm_registration_id"] = $user['gcm_registration_id'];
                $tmp["created_at"] = $user['created_at'];
                array_push($users, $tmp);
            }
        }
 
        return $users;
    }

// messaging in a chat room / to persional message
    public function addMessage($user_id, $chat_room_id, $message) {
        $response = array();
 
        $stmt = $this->conn->prepare("INSERT INTO messages (chat_room_id, user_id, message) values(?, ?, ?)");
        $stmt->bind_param("iis", $chat_room_id, $user_id, $message);
 
        $result = $stmt->execute();
 
        if ($result) {
            $response['error'] = false;
 
            // get the message
            $message_id = $this->conn->insert_id;
            $stmt = $this->conn->prepare("SELECT message_id, user_id, chat_room_id, message, created_at FROM messages WHERE message_id = ?");
            $stmt->bind_param("i", $message_id);
            if ($stmt->execute()) {
                $stmt->bind_result($message_id, $user_id, $chat_room_id, $message, $created_at);
                $stmt->fetch();
                $tmp = array();
                $tmp['message_id'] = $message_id;
                $tmp['chat_room_id'] = $chat_room_id;
                $tmp['message'] = $message;
                $tmp['created_at'] = $created_at;
                $response['message'] = $tmp;
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Failed send message';
        }
 
        return $response;
    }
 
    

    //
    //
    



}

?>
