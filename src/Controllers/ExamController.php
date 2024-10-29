<?php 

namespace App\Controllers;

use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;

class ExamController extends BaseController
{
    public function registrationForm()
    {
        $this->initializeSession();

        return $this->render('registration-form');
    }

    public function register()
    {
        $this->initializeSession();
        $data = $_POST;
        // Save the registration to database
        $_SESSION['user_id'] = 1; // Replace this literal value with the actual user ID from new registration
            $_SESSION['complete_name'] = $data['complete_name'];
            $_SESSION['email'] = $data['email'];

            return $this->render('pre-exam', $data);
    }

    public function exam()
    {
        $this->initializeSession();
        $item_number = 1;

        // If request is coming from the form, save the inputs to the session
        if (isset($_POST['item_number']) && isset($_POST['answer'])) {
            array_push($_SESSION['answers'], $_POST['answer']);
            $_SESSION['item_number'] = $_POST['item_number'] + 1;
        }

        if (!isset($_SESSION['item_number'])) {
            // Initialize session variables
            $_SESSION['item_number'] = $item_number;
            $_SESSION['answers'] = [false];
        } else {
            $item_number = $_SESSION['item_number'];
        }

        $data = $_POST;
        $questionObj = new Question();
        $question = $questionObj->getQuestion($item_number);

        // if there are no more questions, save the answers
        if (is_null($question) || !$question) {
            $user_id = $_SESSION['user_id'];
            $json_answers = json_encode($_SESSION['answers']);

            error_log('FINISHED EXAM, SAVING ANSWERS');
            error_log('USER ID = ' . $user_id);
            error_log('ANSWERS = ' . $json_answers);

            $userAnswerObj = new UserAnswer();
            $userAnswerObj->save(
                $user_id,
                $json_answers
            );
            $score = $questionObj->computeScore($_SESSION['answers']);
            $items = $questionObj->getTotalQuestions();
            $userAnswerObj->saveAttempt($user_id, $items, $score);

            header("Location: /result");
            exit;
        }

        $question['choices'] = json_decode($question['choices']);

        return $this->render('exam', $question);
    }

    public function result()
    {
        $this->initializeSession();
        $data = $_SESSION;
        $questionObj = new Question();
        $data['questions'] = $questionObj->getAllQuestions();
        $answers = $_SESSION['answers'];
        foreach ($data['questions'] as &$question) {
            $question['choices'] = json_decode($question['choices']);
            $question['user_answer'] = $answers[$question['item_number']];
        }
        $data['total_score'] = $questionObj->computeScore($_SESSION['answers']);
        $data['question_items'] = $questionObj->getTotalQuestions();

        session_destroy();

        return $this->render('result', $data);
    }

    public function loginForm()
    {
        $this->initializeSession();
        return $this->render('login'); // Render the login mustache file
    }

    public function login()
    {
        $this->initializeSession();
        $data = $_POST;
    
        $userModel = new User();
        // Verify the user's email and password
        if ($userModel->verifyAccess($data['email'], $data['password'])) {
            // Fetch the user ID and other necessary details
            $sql = "SELECT id, complete_name FROM users WHERE email = :email"; // Prepare SQL query
            $statement = $this->db->prepare($sql); // Prepare statement
            $statement->execute(['email' => $data['email']]); // Execute with the email
            $user = $statement->fetch(PDO::FETCH_ASSOC); // Fetch user data
            
            if ($user) {
                // Store user ID and other details in the session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['complete_name'] = $user['complete_name'];
                // Redirect to exam page
                header("Location: /exam");
                exit;
            }
        } else {
            // Handle login failure (e.g., set an error message)
            return $this->render('login', ['error' => 'Invalid credentials.']);
        }
    }
}
