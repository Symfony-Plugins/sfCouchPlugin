// This counts the amount of the numbers 0-9 in the document ids
// it has no real use, it's just an example that works with every
// document, regardless of the keys it contains.
function (keys, values, rereduce) {
	var counts = {};

	// init the array
	for (var i=0; i<10; i++) {
		counts[i] = 0;
	}
	
    // This is the reduce phase, we are reducing over emitted values from
    // the map functions.
    for(var i in values) {
      if (!rereduce) {
        var chars = values[i].split("");
      } else {
        var chars = values[i]; 
      }
      for (var j=0; j<chars.length; j++) {
        if (chars[j] < 10) {
          counts[chars[j]] = counts[chars[j]]+1;
        }
      }
    };

    // the reduce result. It contains enough information to be rereduced
    // with other reduce results.
    return counts;
};