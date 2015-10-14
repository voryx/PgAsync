<?php


namespace PgAsync\Message;


class Authentication extends Message
{
    const AUTH_OK = 0; // AuthenticationOk
    const AUTH_KERBEROS_V_5 = 2; // AuthenticationKerberosV5
    const AUTH_CLEARTEXT_PASSWORD = 3; // AuthenticationCleartextPassword
    const AUTH_MD5_PASSWORD = 5; // AuthenticationMD5Password
    const AUTH_SCM_CREDENTIAL = 6; // AuthenticationSCMCredential
    const AUTH_GSS = 7; // AuthenticationGSS
    const AUTH_GSS_CONTINUE = 8; // AuthenticationGSSContinue
    const AUTH_SSPI = 9; // AuthenticationSSPI

    private $authCode;

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        $authCode = unpack("N", substr($rawMessage, 5, 4))[1];
        switch ($authCode) {
            case $this::AUTH_OK: break; // AuthenticationOk
            case $this::AUTH_KERBEROS_V_5: break; // AuthenticationKerberosV5
            case $this::AUTH_CLEARTEXT_PASSWORD: break; // AuthenticationCleartextPassword
            case $this::AUTH_MD5_PASSWORD: break; // AuthenticationMD5Password
            case $this::AUTH_SCM_CREDENTIAL: break; // AuthenticationSCMCredential
            case $this::AUTH_GSS: break; // AuthenticationGSS
            case $this::AUTH_GSS_CONTINUE: break; // AuthenticationGSSContinue
            case $this::AUTH_SSPI: break; // AuthenticationSSPI
        }

        $this->authCode = $authCode;
    }

    /**
     * @inheritDoc
     */
    static public function getMessageIdentifier()
    {
        return 'R';
    }

    /**
     * @return mixed
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }
}