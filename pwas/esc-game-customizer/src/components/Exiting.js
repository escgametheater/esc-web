import React, { PureComponent, Fragment } from "react";
import LoadingSpinner from "./LoadingSpinner";

export default class Exiting extends PureComponent {
  render() {
    return (
      <Fragment>
        <div style={{
          position: "fixed",
          top: 0,
          left: 0,
          bottom: 0,
          right: 0,
          opacity: ".5",
          background: "grey",
          zIndex: 1,
        }} />
        <LoadingSpinner
          style={{
            position: "fixed",
            top: "calc(50% - 32px)",
            left: "calc(50% - 32px)",
            zIndex: 2,
          }}
        />
      </Fragment>
    );
  }
}
