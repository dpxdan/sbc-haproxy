<configuration name="json_cdr.conf" description="JSON CDR">
    <settings>

      <!-- Global parameters -->
      <!--<param name="log-b-leg" value="true"/>-->
      <param name="log-b-leg" value="true"/>
      <param name="prefix-a-leg" value="false"/>

      <!-- Whether to URL encode the individual JSON values. Defaults to true, set to false for standard JSON. -->
      <param name="encode-values" value="false"/>

      <!-- Normally if url and log-dir are present, url is attempted first and log-dir second. This options allows to do both systematically. -->
      <!--<param name="log-http-and-disk" value="false"/> -->
      <param name="log-http-and-disk" value="{{LOG_DISK}}"/>

      <!-- File logging -->
      <param name="log-dir" value="{{LOG_DIR}}"/>
      <param name="rotate" value="false"/>

      <!-- HTTP(S) logging -->
      <param name="url" value="{{URL}}"/>
      <param name="auth-scheme" value="{{AUTH_SCHEME}}"/>
      <param name="cred" value="{{CRED}}"/>
      <param name="encode" value="{{ENCODE}}"/>
      <param name="retries" value="{{RETRIES}}"/>
      <param name="delay" value="{{DELAY}}"/>
      <param name="disable-100-continue" value="{{DISABLE_100_CONTINUE}}"/>
      <param name="err-log-dir" value="{{ERR_LOG_DIR}}"/>

      <!-- SSL options -->
      <param name="ssl-key-path" value="{{SSL_KEY_PATH}}"/>
      <param name="ssl-key-password" value="{{SSL_KEY_PASSWORD}}"/>
      <param name="ssl-version" value="{{SSL_VERSION}}"/>
      <param name="enable-ssl-verifyhost" value="{{ENABLE_SSL_VERIFYHOST}}"/>
      <param name="ssl-cert-path" value="{{SSL_CERT_PATH}}"/>
      <param name="enable-cacert-check" value="{{ENABLE_CACERT_CHECK}}"/>
      <param name="ssl-cacert-file" value="{{SSL_CACERT_FILE}}"/>
    </settings>
</configuration>