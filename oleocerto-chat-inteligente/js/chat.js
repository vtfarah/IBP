jQuery(document).ready(function($){
    var chatOpen = false;
    var container = $('<div>', {id: 'oleocerto-chat-box'}).hide();
    var toggle = $('<button>', {id: 'oleocerto-chat-toggle', text: 'Chat'});
    var messages = $('<div>', {class: 'chat-messages'});
    var input = $('<input>', {type: 'text', placeholder: 'Digite sua pergunta...'});
    var sendBtn = $('<button>', {text: 'Enviar'});

    container.append(messages).append(input).append(sendBtn);
    $('body').append(container).append(toggle);

    toggle.on('click', function(){
        chatOpen = !chatOpen;
        container.toggle(chatOpen);
    });

    function appendMessage(text, type){
        messages.append($('<div>', {class: 'msg '+type, text: text}));
    }

    sendBtn.on('click', function(){
        var msg = input.val();
        if(!msg) return;
        appendMessage(msg, 'user');
        input.val('');
        $.post(oleocertoChat.ajaxUrl, {
            action: 'oleocerto_chat',
            message: msg,
            nonce: oleocertoChat.nonce
        }, function(res){
            if(res.success){
                appendMessage(res.data.answer, 'bot');
                if(res.data.related){
                    res.data.related.forEach(function(p){
                        var link = $('<a>', {href:p.link, text:p.title, target:'_blank'});
                        var item = $('<div>', {class:'related'}).append(link);
                        if(p.image){
                            item.prepend($('<img>', {src:p.image, alt:p.title}));
                        }
                        if(p.excerpt){
                            item.append($('<p>',{text:p.excerpt}));
                        }
                        messages.append(item);
                    });
                }
            } else {
                appendMessage('Erro: '+res.data, 'bot');
            }
        });
    });
});
