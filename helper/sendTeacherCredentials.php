<?php

function sendTeacherCredentials(string $email, string $password): bool
{
    $subject = "Qasja juaj në E-Shkolla";
    $message = "
        Përshëndetje,

        Llogaria juaj është krijuar me sukses.

        Email: {$email}
        Fjalëkalimi: {$password}

        Ju lutemi ndryshoni fjalëkalimin pas kyçjes së parë.

        https://e-shkolla.com/login
    ";

    $headers = "From: no-reply@e-shkolla.com";

    return mail($email, $subject, $message, $headers);
}
