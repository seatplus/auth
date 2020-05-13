# auth
handels authentication for web and eveapi

# Usage

## Add more scopes
By default the minimal scopes are requested for users. However one might add scopes to an existing user by adding
a query parameters stating comma separated which scopes should be add:
```
/eve/sso/{character_id?}/step_up?add_scopes=scope1,scope2
```
