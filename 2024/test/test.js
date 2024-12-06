const tmi = require('tmi.js');

const client = new tmi.Client({
	channels: [ 'sequisha' ]
});

client.connect();
var uzytkownicy = [];
var index=0;
client.on('message', (channel, tags, message, self) => {
	
	console.log(`${tags['display-name']}: ${message}`);
	
	const displayName = `${tags['display-name']}`;
	let is=uzytkownicy.find(u => u.id == displayName);

	if (!is){
		uzytkownicy.push({ id: displayName, value: 1 });
	}else{
    	is.value += 1;
	}

	console.log(uzytkownicy);


});
		