function form_validate(form)
{
    set_values();

    var errors = new Array ();
    for (var i = 0; i < form.elements.length; i++)
    {
        var element = document.getElementById(form.elements[i].id);
        if (isDefined(element, 'properties'))
        {
            for (property in element.properties)
            {
                element.value = element.value.replace(/(^\s+)|(\s+$)/g, "");
            	switch (property)
                {
                    case 'allowBlank':
                        switch (element.properties[property])
                        {
                            case '':
                            case '0':
                            case 'false':
                            case false:
                                if (element.value.length == 0)
                                {
                                    errors.push('Поле <b>'+ element.properties['label']+'</b> не должно быть пустым');
                                }
                                break;
                            default:
                                break;
                        }
                        break;
                    case 'regexp':
                        switch (element.properties[property])
                        {
                            case 'numeric':
                                if (!/[\d\.]+/.test(element.value))
                                {
                                    errors.push('Поле <b>'+ element.properties['label']+'</b> должно быть цифровым');
                                }
                                break;
                            case 'email':
                                if (!/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(element.value))
                                {
                                    errors.push('Поле <b>'+element.properties['label']+'</b> должно быть как email');
                                }
                                break;
                            case 'string':
                                if (!/^[a-zA-Zа-яёА-ЯЁ\.\-\_ ]+$/.test(element.value))
                                {
                                    errors.push('Поле <b>'+ element.properties['label']+'</b> должно быть строкой');
                                }
                                break;
                            case 'text':
                                if (!/[\w\s\dа-яёА-ЯЁ]+/.test(element.value))
                                {
                                    errors.push('Поле <b>'+ element.properties['label']+'</b> не должно быть пустым');
                                }
                                break;
                            default:
                                if (!element.properties[property].test(element.value))
                                {
                                    errors.push('Поле <b>'+ element.properties['label']+'</b> не соответствует формату');
                                }
                                break;
                        }
                      break;
                }
            }
        }
    }
    if (errors.length == 0)
    {
        return true;
    }
    else
    {
        document.getElementById('errors').innerHTML = errors.join("<br/>");
        var myWindow = document.getElementById('error_message');
        trigger('error_message');

        return false;
    }
}

function isDefined(object, variable)
{
    return (typeof(eval(object)[variable]) != 'undefined');
}

function trigger(nr)
{

    document.getElementById(nr).style.display = 'block';
    document.getElementById(nr).style.visibility = 'visible';
    /*
    var submit = document.getElementById('submit');

    var disabled = (submit.disabled == false) ? true : false
    submit.disabled = disabled;

    if (document.layers)
    {
        vista = (document.layers[nr].visibility == 'hide') ? 'show' : 'hide'
        document.layers[nr].visibility = vista;
        current = (document.layers[nr].display == 'none') ? 'block' : 'none';
        document.layers[nr].display = current;
    }
    else if (document.all)
    {
        vista = (document.all[nr].style.visibility == 'hidden') ? 'visible' : 'hidden';
        document.all[nr].style.visibility = vista;
        current = (document.all[nr].style.display == 'none') ? 'block' : 'none';
        document.all[nr].style.display = current;
    }
    else if (document.getElementById)
    {
        vista = (document.getElementById(nr).style.visibility == 'hidden') ? 'visible' : 'hidden';
        document.getElementById(nr).style.visibility = vista;
        vista = (document.getElementById(nr).style.display == 'none') ? 'block' : 'none';
        document.getElementById(nr).style.display = vista;
    }
	*/
}
