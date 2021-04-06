Simple Data Access Control
=====================

Simple Data Access Control allows the restriction of which user can access which resources, in the way compatible with Advanced Search.

Access Privileges are granted either to users directly or to roles, applying to all users who have that specific role.

Privileges are given per resource, so that in order to remove the write access to all items within a class, the new access rights need to be applied recursively to all resources by checking "recursive" before saving the changes.

Privileges are additive, meaning that if:

* Role A has write and read access to Item 1
* User X has read access to Item 1
* And User X has the Role A

Then User X he will have read and write access to Item 1

## How to enable ACL management

### Enable this in the actions

Change the actions/structures.xml file by adding the actions attribute `allowClassActions="true"`:

```xml
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE structures SYSTEM "../../tao/doc/structures.dtd">
<structures>
    <structure>
        <sections>
            <section id="manage_items" name="Manage items" url="/taoItems/Items/index">
                <trees>
                  <!-- Something here -->
                </trees>
                <actions allowClassActions="true">
                    <action>
                      <!-- Something here -->    
                    </action>
                </actions>
            </section>
        </sections>
    </structure>
</structures>

```

### How support ACL in an endpoint? 

Add the following annotation with proper `field` and `grant level` to check:

```php
class MyController extends tao_actions_SaSModule
{
    /**
     * @requiresRight id READ
     */
    public function editInstance()
    {
      //...
    }
}
```

### How to check for permission inside the endpoint implementation?

On RDF controller, we can use a single method

```php
class MyController extends tao_actions_SaSModule
{
    /**
     * Edit an item instance
 * 
     * @requiresRight id READ
     */
    public function editItem()
    {
        $item = $this->getCurrentInstance();

        $itemUri = $item->getUri();
            
        if ($this->hasWriteAccess($itemUri)) {
            // Do something
        }
    }
}
```

Or using the method:

```php
use oat\tao\model\accessControl\data\DataAccessControl;

$user = $this->getSession()->getUser();

$canWrite = (new DataAccessControl())->hasPrivileges($user, [$resourceId => 'WRITE']);
$canRead = (new DataAccessControl())->hasPrivileges($user, [$resourceId => 'READ']);
```
