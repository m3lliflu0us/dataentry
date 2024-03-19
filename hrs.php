<?php
/*
 * Hour Registration Program(hrs)
 * Author: Bryce van der Werf
 * Version: 1.0
 * Date: 19/03/2024
 * Description: This program allows users to register their hours.
 *              It is intended for use on Windows systems with PHP environment set up.
 */

// Contributors
/*
 * - Bryce van der Werf: Developer - bryce.van.der.werf@student.gildeopleidingen.nl
 */

// Note: Ensure that you have appropriate permissions to execute PHP scripts and access the directory where the program files are located.
// Make sure to have a text editor installed on your system to view and edit the PHP script if necessary.

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "uren_registratie_systeem";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function hidePasswordInput()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            echo "Enter your password: ";
            $cmd = 'powershell -Command "$pwd = read-host -AsSecureString; $BSTR=[System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($pwd); [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)"';
            $password = trim(shell_exec($cmd));
            echo "\n";
        } else {
            system('stty -echo');
            echo "Enter your password: ";
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        }

        return $password;
    }

    function createUser($conn)
    {
        $name = readline("Enter your name: ");
        $email = readline("Enter your email: ");
        $password = password_hash(hidePasswordInput(), PASSWORD_DEFAULT);

        $insertUserQuery = $conn->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
        $insertUserQuery->bindParam(":name", $name);
        $insertUserQuery->bindParam(":email", $email);
        $insertUserQuery->bindParam(":password", $password);
        $insertUserQuery->execute();

        $userId = $conn->lastInsertId();
        $insertUserQuery->closeCursor();

        return $userId;
    }

    // Add a new function to get cooldown expiry
    function getCooldownExpiry($conn, $userId)
    {
        $selectCooldownQuery = $conn->prepare("SELECT cooldown_expiry FROM cooldown WHERE user_id = :userId");
        $selectCooldownQuery->bindParam(":userId", $userId);
        $selectCooldownQuery->execute();

        $cooldownExpiry = $selectCooldownQuery->fetchColumn();
        $selectCooldownQuery->closeCursor();

        return $cooldownExpiry;
    }

    // Modify the loginUser function
    function loginUser($conn)
    {
        echo "Log in to your account:\n";

        $maxAttempts = 1;

        $email = readline("Enter your email: ");
        $attemptCount = 0;

        // Fetch user details including cooldown expiry
        $selectUserQuery = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $selectUserQuery->bindParam(":email", $email);
        $selectUserQuery->execute();
        $user = $selectUserQuery->fetch(PDO::FETCH_ASSOC);
        $selectUserQuery->closeCursor();

        if (!$user) {
            echo "User not found, please create an account.\n";
            return false;
        }

        // Check if the account is on cooldown
        $cooldownExpiry = getCooldownExpiry($conn, $user['id']);

        if ($cooldownExpiry && strtotime($cooldownExpiry) > time()) {
            // Account is on cooldown
            echo "Account is on cooldown, please try again later.\n";
            return false;
        }

        do {
            if ($attemptCount > 0) {
                echo "Incorrect password. Attempts left: " . ($maxAttempts - $attemptCount) . "\n";
            }

            $password = hidePasswordInput();

            if (password_verify($password, $user['password'])) {
                // Successful login, reset cooldown
                resetCooldown($conn, $user['id']);
                return $user['id'];
            } else {
                $attemptCount++;
            }

            if ($attemptCount >= $maxAttempts) {
                echo "Account is on cooldown, please try again later.\n";
                saveCooldown($conn, $user['id'], 300); // Assuming cooldown is 5 minutes
                countdown(300);

                $attemptCount = 0;
                return false;
            }
        } while ($attemptCount < $maxAttempts);
    }

    function saveCooldown($conn, $userId, $cooldownDuration)
    {
        $cooldown_expiry = date('Y-m-d H:i:s', time() + $cooldownDuration);

        $insertCooldownQuery = $conn->prepare("INSERT INTO cooldown (user_id, cooldown_expiry) VALUES (:userId, :cooldown_expiry) ON DUPLICATE KEY UPDATE cooldown_expiry = :cooldown_expiry");
        $insertCooldownQuery->bindParam(":userId", $userId);
        $insertCooldownQuery->bindParam(":cooldown_expiry", $cooldown_expiry);
        $insertCooldownQuery->execute();
        $insertCooldownQuery->closeCursor();
    }

    function resetCooldown($conn, $userId)
    {
        $deleteCooldownQuery = $conn->prepare("DELETE FROM cooldown WHERE user_id = :userId");
        $deleteCooldownQuery->bindParam(":userId", $userId);
        $deleteCooldownQuery->execute();
        $deleteCooldownQuery->closeCursor();
    }

    function countdown($seconds)
    {
        while ($seconds > 0) {
            echo "Cooldown: " . gmdate("i:s", $seconds) . "\r";
            sleep(1);
            $seconds--;
        }
        echo "Cooldown complete, you can try again now.\n";
    }

    echo "Welcome to the hour registration system!\n";
    echo "Made By A3.\n";

    while (true) {
        $choice = strtolower(readline("Do you have an account? (yes/no): \n"));

        if ($choice === "yes" || $choice === "y" || $choice === "ye") {
            $userId = loginUser($conn);
            if ($userId !== false) {
                break;
            }
        } elseif ($choice === "no" || $choice === "n") {
            $userId = createUser($conn);
            break;
        } else {
            echo "Invalid choice, please enter 'yes' or 'no'.\n";
        }
    }

    echo "You are now logged in!\n";

    $numEntries = intval(readline("How many entries do you want to registrate? "));

    for ($i = 1; $i <= $numEntries; $i++) {
        $logDate = readline("Enter log date (YYYY-MM-DD): ");
        $project = readline("Enter project name: ");
        $beginTime = readline("Enter begin time (HH:MM): ");
        $endTime = readline("Enter end time (HH:MM): ");

        $insertDataQuery = $conn->prepare("INSERT INTO data (user_id, log_date, project, begin_time, end_time) VALUES (:userId, :logDate, :project, :beginTime, :endTime)");
        $insertDataQuery->bindParam(":userId", $userId);
        $insertDataQuery->bindParam(":logDate", $logDate);
        $insertDataQuery->bindParam(":project", $project);
        $insertDataQuery->bindParam(":beginTime", $beginTime);
        $insertDataQuery->bindParam(":endTime", $endTime);

        if ($insertDataQuery->execute()) {
            echo "Data successfully registered for entry $i.\n";
        } else {
            echo "Error registering data for entry $i: " . $insertDataQuery->errorInfo()[2] . "\n";
        }

        $insertDataQuery->closeCursor();
    }

    $conn = null;
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
