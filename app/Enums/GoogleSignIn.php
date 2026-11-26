<?php

namespace App\Enums;

/** What "continue with Google" ended in. */
enum GoogleSignIn
{
    /** An existing account, now signed in (linked to Google if it was not yet). */
    case SignedIn;
    /** A new account was created and signed in. */
    case Created;
    /** Google did not confirm the email, the account is stopped, or it is linked to another Google account. */
    case Refused;
}
