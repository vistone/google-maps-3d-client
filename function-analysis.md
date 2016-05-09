`_.z` appears to be the most commonly called function

Here's the original function.
```
_.z = function (a) {
	var b = _.rd.Bb();
	b.isDisposed() || (oda(b.H[a], (0, _.v)(b.La, b)) && Oda(b, 4), _.zc(b.V, a), _.zc(b.T, a), 0 == b.T.length && Wda(b), b.Ha && a == b.Ha && (b.va.R || b.va.$b()), sd(b))
};
_.Xb = function (a) {
	a.Bb = function () {
		return a.HI ? a.HI : a.HI = new a
	}
};
_.Xb(_.rd);
```

Here's my interperation of the function.

```
_.z = function (a) {
	var b = _.rd.Bb();
	if (b.isDisposed()) {
	  return;
	}
	(
	  oda(b.H[a], (0, _.v)(b.La, b))
	  && Oda(b, 4)
	  , _.zc(b.V, a)
	  , _.zc(b.T, a)
	  , 0 == b.T.length && Wda(b)
	  , b.Ha && a == b.Ha && (b.va.R || b.va.$b())
	  , sd(b)
	)
};

_.rd.Bb = function() {
  // If HI doesn't exist, then create it. Lastly, return HI.
	if (_.rd.HI) {
  	return a.HI;
  }
  _.rd.HI = new _.rd;
  return a.HI;
};
```
